<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameCategory;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Launch\Models\PlaySession;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(fn () => Cache::flush());

/** @return array{0: Operator, 1: string} the operator and a lobby launch token */
function lobbyLaunch(array $config = []): array
{
    $operator = Operator::factory()->create(['config' => $config]);

    return [$operator, app(LaunchService::class)->issue($operator, null, 'p-1', 'EUR')->token];
}

it('opens the lobby with the operator\'s theme, and lets the operator\'s origins embed it', function (): void {
    [, $token] = lobbyLaunch([
        'embed_origins' => 'https://casino.example, https://m.casino.example',
        'lobby_logo_url' => 'https://casino.example/logo.svg',
        'lobby_primary_color' => '#123abc',
    ]);

    $response = get("/lobby?token={$token}");

    $response->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertSee('--primary: #123abc', false)
        ->assertSee('https://casino.example/logo.svg', false)
        ->assertSee('play-host.js', false)
        // The host script's bridge import resolves to a versioned URL, so a deploy reaches browsers at once.
        ->assertSee(json_encode(asset('js/orange/bridge.js').'?v='.filemtime(public_path('js/orange/bridge.js'))), false);
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain('https://casino.example')
        ->toContain('https://m.casino.example');
});

it('falls back to the default look for anything that is not a safe logo URL or a hex colour', function (): void {
    $operator = Operator::factory()->create(['config' => [
        'lobby_logo_url' => 'javascript:alert(1)', 'lobby_primary_color' => 'red;}body{display:none',
    ]]);

    expect($operator->lobbyTheme())->toBe(['logo_url' => null, 'primary' => '#ff8a00', 'accent' => '#22c55e', 'background' => '#04121f']);
});

it('refuses a game launch token at the lobby, and a used one', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $gameOperator = Operator::factory()->create();
    OperatorGame::factory()->direct()->create(['operator_id' => $gameOperator->id, 'game_id' => $game->id]);
    $gameToken = app(LaunchService::class)->issue($gameOperator, $game, 'p-1', 'EUR')->token;
    [, $token] = lobbyLaunch();

    get("/lobby?token={$gameToken}")->assertNotFound();
    get("/lobby?token={$token}")->assertOk();
    get("/lobby?token={$token}")->assertStatus(410);
    expect(PlaySession::query()->whereNotNull('revoked_at')->count())->toBe(1);
});

it('lists the operator\'s playable games by category, with recent and popular', function (): void {
    $crashCategory = GameCategory::query()->where('slug', 'crash')->sole();
    $originals = GameCategory::query()->where('slug', 'originals')->sole();
    $originals->forceFill(['sort_order' => 1])->save();
    [$operator, $token] = lobbyLaunch();
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'name' => 'DeepDive', 'category_id' => $crashCategory->id]);
    $dice = Game::factory()->create(['code' => 'dice', 'slug' => 'dice', 'type' => 'dice', 'name' => 'Dice', 'access' => GameAccess::Listed,
        'category_id' => $originals->id, 'rtp_target' => 0.99, 'house_edge' => 0.01]);
    OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $dice->id]);
    OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $crash->id]);
    Game::factory()->create(['code' => 'other-dice', 'slug' => 'other-dice', 'type' => 'dice', 'access' => GameAccess::Listed]);
    $rocket = Game::factory()->create(['code' => 'rocket', 'slug' => 'rocket', 'name' => 'Rocket', 'status' => 'maintenance']);
    OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $rocket->id]);
    // Relay-only for this operator: not offered in the lobby.
    $relayed = Game::factory()->create(['code' => 'relayed', 'slug' => 'relayed', 'name' => 'Relayed']);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $relayed->id, 'settings' => ['play_mode' => 'relay']]);
    $player = Player::query()->where('external_player_id', 'p-1')->sole();
    Bet::factory()->create(['operator_id' => $operator->id, 'player_id' => $player->id, 'round_id' => null, 'game_id' => $dice->id]);
    Bet::factory()->create(['operator_id' => $operator->id, 'player_id' => $player->id, 'round_id' => null, 'game_id' => $crash->id]);
    Bet::factory()->create(['operator_id' => $operator->id, 'player_id' => $player->id, 'round_id' => null, 'game_id' => $crash->id]);
    [, $bearer] = app(PlaySessionService::class)->exchange($token);

    $response = getJson('/play-api/lobby', ['Authorization' => "Bearer {$bearer}"])->assertOk();

    expect(array_column($response->json('games'), 'code'))->toEqualCanonicalizing(['crash', 'dice', 'rocket'])
        ->and(array_column($response->json('categories'), 'slug'))->toBe(['originals', 'crash'])
        ->and($response->json('recent'))->toBe(['crash', 'dice'])
        ->and($response->json('popular'))->toBe(['crash', 'dice'])
        ->and($response->json('theme.primary'))->toBe('#ff8a00');
    $tiles = array_column((array) $response->json('games'), null, 'code');
    expect($tiles['dice']['frame_url'])->toBe(url('/games/dice').'?game=dice')
        ->and($tiles['crash']['frame_url'])->toBe(url('/crash').'?game=crash')
        ->and($tiles['crash']['rtp'])->toBe('0.9700')
        ->and($tiles['rocket']['playable'])->toBeFalse()
        ->and($tiles['dice']['playable'])->toBeTrue();
});

it('serves lobby data only to lobby sessions', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    [, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);

    getJson('/play-api/lobby', ['Authorization' => "Bearer {$bearer}"])->assertForbidden()->assertJsonPath('error', 'not_a_lobby_session');
});
