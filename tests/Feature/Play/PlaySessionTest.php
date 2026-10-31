<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;
use App\Domain\Launch\Exceptions\InvalidLaunchToken;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(fn () => Cache::flush());
afterEach(fn () => Carbon::setTestNow());

function launchFor(?Game $game = null, ?Operator $operator = null): string
{
    return app(LaunchService::class)
        ->issue($operator ?? Operator::factory()->create(), $game, 'p-1', 'EUR', 'tr')
        ->token;
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
        ->assertSee(json_encode(url('/crash')), false)
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
        ->assertJsonPath('player.currency', 'EUR')
        ->assertJsonPath('game', 'crash')
        ->assertJsonPath('lobby', false)
        ->assertJsonPath('language', 'tr');
});
