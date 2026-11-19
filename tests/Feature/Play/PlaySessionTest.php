<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Launch\Exceptions\InvalidLaunchToken;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(fn () => Cache::flush());
afterEach(fn () => Carbon::setTestNow());

function launchFor(?Game $game = null, ?Operator $operator = null): string
{
    $operator ??= Operator::factory()->create();
    if ($game !== null) {
        OperatorGame::query()->firstOrCreate(['operator_id' => $operator->id, 'game_id' => $game->id], ['enabled' => true, 'settings' => ['play_mode' => 'direct']]);
    }

    return app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR', 'tr')->token;
}

it('exchanges a launch token for a play session exactly once', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $token = launchFor($game);
    $sessions = app(PlaySessionService::class);

    [$session, $bearer] = $sessions->exchange($token);

    expect($bearer)->toStartWith('ps_')
        ->and($session->game_id)->toBe($game->id)
        ->and($session->lobby)->toBeFalse()
        ->and($session->currency)->toBe('EUR')
        ->and($sessions->authenticate($bearer)?->id)->toBe($session->id)
        ->and(PlaySession::query()->where('token_hash', $bearer)->exists())->toBeFalse()
        ->and(fn () => $sessions->exchange($token))->toThrow(InvalidLaunchToken::class);
});

it('refuses an expired launch token, and stops authenticating an expired session', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $stale = launchFor($game);
    [, $bearer] = app(PlaySessionService::class)->exchange(launchFor($game));

    Carbon::setTestNow(now()->addMinutes(3));
    expect(fn () => app(PlaySessionService::class)->exchange($stale))->toThrow(InvalidLaunchToken::class);

    Carbon::setTestNow(now()->addHours(5));
    expect(app(PlaySessionService::class)->authenticate($bearer))->toBeNull();
});

it('opens the host page for the launched game, with the game frame and no-store, no-referrer headers', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'name' => 'DeepDive']);
    $operator = Operator::factory()->create(['config' => ['embed_origins' => ['https://casino.example']]]);

    $response = get('/play/crash?token='.launchFor($game, $operator));

    $response->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('play-host.js', false)
        // The host script's bridge import resolves to a versioned URL, so a deploy reaches browsers at once.
        ->assertSee(json_encode(asset('js/orange/bridge.js').'?v='.filemtime(public_path('js/orange/bridge.js'))), false)
        ->assertSee(json_encode(url('/crash').'?game=crash'), false)
        ->assertSee('"ps_', false)
        ->assertSee('https:\/\/casino.example', false);
    expect($response->headers->get('Content-Security-Policy'))->toContain('https://casino.example');
});

it('shows the expired page for a used, unknown or mismatched token', function (): void {
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    Game::factory()->create(['code' => 'other', 'slug' => 'other']);
    $token = launchFor($crash);
    get("/play/crash?token={$token}")->assertOk();

    get("/play/crash?token={$token}")->assertStatus(410)->assertSee('expired');
    get('/play/crash?token=lt_nope')->assertStatus(410);
    get('/play/other?token='.launchFor($crash))->assertNotFound();
    expect(PlaySession::query()->whereNotNull('revoked_at')->count())->toBe(1);
});

it('resumes on reload without a token, leaving the session to the tab', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);

    get('/play/crash')->assertOk()->assertSee('bearer: null', false);
});

it('answers the player API only with a valid session', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    [, $bearer] = app(PlaySessionService::class)->exchange(launchFor($game));

    getJson('/play-api/session')->assertUnauthorized()->assertJsonPath('error', 'session_expired');
    getJson('/play-api/session', ['Authorization' => 'Bearer ps_wrong'])->assertUnauthorized();
    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('player.id', 'p-1')
        ->assertJsonPath('player.username', null)
        ->assertJsonPath('player.currency', 'EUR')
        ->assertJsonPath('game', 'crash')
        ->assertJsonPath('lobby', false)
        ->assertJsonPath('language', 'tr')
        // No wallet call yet: Orange knows no balance, and the operator page's figure is newer.
        ->assertJsonPath('balance', null)
        ->assertJsonPath('balance_before_launch', true)
        ->assertJsonPath('idle_timeout_seconds', 15 * 60);
});

it('ends a session left idle for the idle time, for good, but never early', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $sessions = app(PlaySessionService::class);
    [$session, $bearer] = $sessions->exchange(launchFor($game));

    Carbon::setTestNow(now()->addMinutes(10));
    expect($sessions->authenticate($bearer))->not->toBeNull();
    // last_seen_at moves in one-minute steps, so the server allows that minute on top.
    Carbon::setTestNow(now()->addMinutes(15)->addSeconds(59));
    expect($sessions->authenticate($bearer))->not->toBeNull();

    Carbon::setTestNow(now()->addMinutes(16));
    expect($sessions->authenticate($bearer))->toBeNull()
        ->and($session->refresh()->revoked_at)->not->toBeNull();
    Carbon::setTestNow(now()->addSecond());
    expect($sessions->authenticate($bearer))->toBeNull();
    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])->assertUnauthorized();
});

it('takes the idle time from the operator: minutes, or 0 for never', function (array $config, ?int $seconds): void {
    expect(Operator::factory()->make(['config' => $config])->sessionIdleSeconds())->toBe($seconds);
})->with([
    'the default' => [[], 15 * 60],
    'minutes' => [['session_idle_minutes' => 5], 5 * 60],
    'as typed in the panel' => [['session_idle_minutes' => ' 30 '], 30 * 60],
    'off' => [['session_idle_minutes' => '0'], null],
    'at most a day' => [['session_idle_minutes' => 99999], 24 * 3600],
    'not a number' => [['session_idle_minutes' => 'soon'], 15 * 60],
]);

it('lets an operator turn the idle timeout off', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create(['config' => ['session_idle_minutes' => 0]]);
    [, $bearer] = app(PlaySessionService::class)->exchange(launchFor($game, $operator));

    Carbon::setTestNow(now()->addHours(3));

    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('idle_timeout_seconds', null);
});

it('says whether the balance a wallet call last reported came before this launch', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    [$session, $bearer] = app(PlaySessionService::class)->exchange(launchFor($game, $operator));
    $reported = fn (string $balance) => WalletTransaction::factory()->create([
        'operator_id' => $operator->id,
        'player_id' => $session->player_id,
        'operator_callback_status' => WalletCallbackStatus::Completed,
        'operator_response' => ['status' => 200, 'body' => ['balance_after' => $balance]],
    ]);
    // The player's last bet, an hour before this launch; since then they deposited at the casino.
    $reported('3.00')->forceFill(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()])->save();

    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])
        ->assertJsonPath('balance', '3.00')
        ->assertJsonPath('balance_before_launch', true);

    // A bet in this session: Orange's figure is the newest now.
    Carbon::setTestNow(now()->addMinute());
    $reported('249.00');

    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])
        ->assertJsonPath('balance', '249.00')
        ->assertJsonPath('balance_before_launch', false);
});

it('gives the game the player\'s display name from the launch', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    OperatorGame::query()->create(['operator_id' => $operator->id, 'game_id' => $game->id, 'enabled' => true, 'settings' => ['play_mode' => 'direct']]);
    $token = app(LaunchService::class)->issue($operator, $game, 'client_x:7', 'TRY', username: 'orhuntest')->token;
    [, $bearer] = app(PlaySessionService::class)->exchange($token);

    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])
        ->assertOk()
        ->assertJsonPath('player.id', 'client_x:7')
        ->assertJsonPath('player.username', 'orhuntest');
});

it('ends play sessions and launch links when their operator is suspended', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    $unused = launchFor($game, $operator);
    [, $bearer] = app(PlaySessionService::class)->exchange(launchFor($game, $operator));

    $operator->forceFill(['status' => OperatorStatus::Suspended])->save();

    expect(app(PlaySessionService::class)->authenticate($bearer))->toBeNull()
        ->and(fn () => app(PlaySessionService::class)->exchange($unused))->toThrow(InvalidLaunchToken::class);
    getJson('/play-api/session', ['Authorization' => "Bearer {$bearer}"])->assertUnauthorized();
});
