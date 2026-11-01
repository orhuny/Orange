<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameCategory;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Launch\Models\LaunchToken;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;

beforeEach(function (): void {
    Cache::flush();
    Http::fake();
});

/**
 * A signed operator API request. $path may carry a query string, which is signed as sent.
 *
 * @param  array<string, mixed>|null  $payload
 * @return TestResponse<Response>
 */
function operatorApi(Operator $operator, string $method, string $path, ?array $payload = null, string $key = 'k-1', int $version = 1, ?int $timestamp = null, ?string $signedPath = null): TestResponse
{
    $body = $payload === null ? '' : (string) json_encode($payload);
    $timestamp ??= time();
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id,
        'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute(
            $operator->secret_key, $method, $signedPath ?? $path, $body, $timestamp,
            $version === 2 ? $key : null,
        ),
        'HTTP_IDEMPOTENCY_KEY' => $key,
    ];
    if ($version !== 1) {
        $server['HTTP_X_SIGNATURE_VERSION'] = (string) $version;
    }

    return call($method, $path, [], [], [], $server, $body);
}

function listedGame(string $code, array $attributes = []): Game
{
    return Game::factory()->create(['code' => $code, 'slug' => $code, 'access' => GameAccess::Listed] + $attributes);
}

// --- GET /v1/games -----------------------------------------------------------------------

it('lists crash for every operator, in any currency without limits', function (): void {
    Game::factory()->create(['code' => 'crash', 'slug' => 'crash', 'name' => 'DeepDive']);

    operatorApi(Operator::factory()->create(), 'GET', '/v1/games')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'crash')
        ->assertJsonPath('data.0.type', 'crash')
        ->assertJsonPath('data.0.family', 'round')
        ->assertJsonPath('data.0.currencies', 'any')
        ->assertJsonPath('data.0.limits', [])
        ->assertJsonPath('data.0.rtp', '0.9700')
        ->assertJsonPath('data.0.play_mode', 'relay');
});

it('lists only the games enabled for the operator, with its effective limits per currency', function (): void {
    $operator = Operator::factory()->create();
    $enabled = listedGame('crash-two', ['category_id' => GameCategory::query()->where('slug', 'crash')->value('id')]);
    GameLimit::factory()->create(['game_id' => $enabled->id, 'currency' => 'EUR', 'min_bet' => '0.1', 'max_bet' => '100', 'max_win' => '5000']);
    GameLimit::factory()->create(['game_id' => $enabled->id, 'currency' => 'TRY', 'min_bet' => '5', 'max_bet' => '5000', 'max_win' => '250000']);
    $operatorGame = OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $enabled->id, 'settings' => ['play_mode' => 'direct']]);
    $operatorGame->limits()->create(['currency' => 'EUR', 'max_bet' => '50']);
    listedGame('not-enabled');
    $disabledForOperator = Game::factory()->create(['code' => 'crash-off', 'slug' => 'crash-off']);
    OperatorGame::factory()->disabled()->create(['operator_id' => $operator->id, 'game_id' => $disabledForOperator->id]);
    Game::factory()->create(['code' => 'draft-game', 'slug' => 'draft-game', 'status' => 'draft']);
    Game::factory()->create(['code' => 'no-engine', 'slug' => 'no-engine', 'type' => 'roulette']);

    $response = operatorApi($operator, 'GET', '/v1/games')->assertOk();

    expect(array_column((array) $response->json('data'), 'code'))->toBe(['crash-two']);
    $response->assertJsonPath('data.0.category', 'crash')
        ->assertJsonPath('data.0.currencies', 'listed')
        ->assertJsonPath('data.0.play_mode', 'direct')
        ->assertJsonPath('data.0.limits.EUR', ['min_bet' => '0.10000000', 'max_bet' => '50.00000000', 'max_win' => '5000.00000000'])
        ->assertJsonPath('data.0.limits.TRY.max_win', '250000.00000000');
});

it('signs the query string: a request whose signature leaves it out is refused', function (): void {
    $operator = Operator::factory()->create();

    operatorApi($operator, 'GET', '/v1/games?currency=EUR', key: 'q-1')->assertOk();
    operatorApi($operator, 'GET', '/v1/games?currency=EUR', key: 'q-2', signedPath: '/v1/games')
        ->assertStatus(401)
        ->assertJsonPath('reason', 'signature_mismatch');
});

// --- signature v2 ------------------------------------------------------------------------

it('accepts v2 signatures, which cover the Idempotency-Key', function (): void {
    $operator = Operator::factory()->create();

    operatorApi($operator, 'POST', '/v1/ping', ['a' => 1], 'v2-a', version: 2)->assertCreated();

    // Same signature, different (unsigned in v1) key: refused under v2.
    $timestamp = time();
    $signature = OperatorSignature::compute($operator->secret_key, 'POST', '/v1/ping', '{"a":2}', $timestamp, 'v2-signed');
    call('POST', '/v1/ping', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => $signature, 'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => 'v2-swapped',
    ], '{"a":2}')->assertStatus(401)->assertJsonPath('reason', 'signature_mismatch');
});

it('lets two different v2 requests share a second, where v1 sees a replay (D7)', function (): void {
    $operator = Operator::factory()->create();
    $timestamp = time();

    operatorApi($operator, 'POST', '/v1/ping', null, 'v1-a', timestamp: $timestamp)->assertCreated();
    operatorApi($operator, 'POST', '/v1/ping', null, 'v1-b', timestamp: $timestamp)
        ->assertStatus(401)->assertJsonPath('reason', 'replay_detected');

    operatorApi($operator, 'POST', '/v1/ping', null, 'v2-a', version: 2, timestamp: $timestamp)->assertCreated();
    operatorApi($operator, 'POST', '/v1/ping', null, 'v2-b', version: 2, timestamp: $timestamp)->assertCreated();
});

it('refuses an unknown signature version', function (): void {
    operatorApi(Operator::factory()->create(), 'POST', '/v1/ping', [], version: 3)
        ->assertStatus(401)->assertJsonPath('reason', 'unsupported_signature_version');
});

// --- launch --------------------------------------------------------------------------------

it('launches a game: a single-use token stored only as a hash, and the play URL', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    OperatorGame::factory()->direct()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);

    $response = operatorApi($operator, 'POST', '/v1/games/crash/launch', [
        'player_external_id' => '12*3456', 'currency' => 'EUR', 'language' => 'tr', 'return_url' => 'https://casino.example/games',
    ])->assertCreated();

    $token = (string) $response->json('launch_token');
    expect($token)->toStartWith('lt_')
        ->and(array_keys($response->json()))->toBe(['launch_token', 'play_url', 'expires_at'])
        ->and($response->json('play_url'))->toBe(url('/play/crash').'?token='.$token);

    $stored = LaunchToken::query()->sole();
    expect($stored->token_hash)->toBe(LaunchService::hash($token))
        ->and(LaunchToken::query()->where('token_hash', $token)->exists())->toBeFalse()
        ->and($stored->game_id)->toBe($game->id)
        ->and($stored->currency)->toBe('EUR')
        ->and($stored->language)->toBe('tr')
        ->and(now()->diffInSeconds($stored->getAttribute('expires_at'), true))->toBeLessThanOrEqual(121.0)
        ->and($stored->player->external_player_id)->toBe('12*3456');
});

it('launches the lobby', function (): void {
    $response = operatorApi(Operator::factory()->create(), 'POST', '/v1/lobby/launch', [
        'player_external_id' => 'p-1', 'currency' => 'EUR',
    ])->assertCreated();

    expect(array_keys($response->json()))->toBe(['launch_token', 'lobby_url', 'expires_at'])
        ->and($response->json('lobby_url'))->toStartWith(url('/lobby').'?token=lt_')
        ->and(LaunchToken::query()->sole()->game_id)->toBeNull();
});

it('refuses to launch what the operator could not play', function (string $code, int $status, string $error): void {
    listedGame('listed-off');
    $limited = Game::factory()->create(['code' => 'eur-only', 'slug' => 'eur-only']);
    GameLimit::factory()->create(['game_id' => $limited->id, 'currency' => 'EUR']);

    operatorApi(Operator::factory()->create(), 'POST', "/v1/games/{$code}/launch", [
        'player_external_id' => 'p-1', 'currency' => 'TRY',
    ])->assertStatus($status)->assertJsonPath('error', $error);

    expect(LaunchToken::count())->toBe(0);
})->with([
    'unknown game' => ['nope', 404, 'unknown_game'],
    'not enabled' => ['listed-off', 403, 'game_not_enabled'],
    'unsupported currency' => ['eur-only', 422, 'currency_not_supported'],
]);

// --- POST /v1/games/{code}/bets --------------------------------------------------------------

function bettingRoundOf(Game $game): GameRound
{
    $orchestrator = app(RoundOrchestrator::class);

    return $orchestrator->openBetting($orchestrator->create($game));
}

it('places a crash bet on the round taking bets when no round is given', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $round = bettingRoundOf($game);

    $response = operatorApi(Operator::factory()->create(), 'POST', '/v1/games/crash/bets', [
        'player_external_id' => 'p-1', 'amount' => '2.00', 'currency' => 'EUR', 'params' => ['auto_cashout_at' => '2.50'],
    ])->assertCreated();

    expect(array_keys($response->json()))->toBe(['bet_id', 'game', 'status', 'round_uuid', 'amount', 'currency', 'wallet_tx_id'])
        ->and($response->json('round_uuid'))->toBe($round->round_uuid)
        ->and($response->json('game'))->toBe('crash');
    $bet = Bet::sole();
    expect((string) $bet->auto_cashout_at)->toBe('2.50')
        ->and($bet->game_id)->toBe($game->id);
});

it('refuses a crash bet on another game\'s round, with bad params, or when betting is closed', function (): void {
    $crash = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $other = Game::factory()->create();
    $otherRound = bettingRoundOf($other);
    $operator = Operator::factory()->create();
    $bet = ['player_external_id' => 'p-1', 'amount' => '1.00', 'currency' => 'EUR'];

    operatorApi($operator, 'POST', '/v1/games/crash/bets', $bet, 'b-1')
        ->assertStatus(409)->assertJsonPath('error', 'betting_closed');
    operatorApi($operator, 'POST', '/v1/games/crash/bets', $bet + ['round_uuid' => $otherRound->round_uuid], 'b-2')
        ->assertStatus(404)->assertJsonPath('error', 'unknown_round');

    bettingRoundOf($crash);
    operatorApi($operator, 'POST', '/v1/games/crash/bets', $bet + ['params' => ['auto_cashout_at' => '1.00']], 'b-3')
        ->assertStatus(422)->assertJsonPath('error', 'invalid_params');
    operatorApi($operator, 'POST', '/v1/games/nope/bets', $bet, 'b-4')
        ->assertStatus(404)->assertJsonPath('error', 'unknown_game');

    expect(Bet::count())->toBe(0);
});

it('refuses to launch a game the operator plays in relay mode', function (): void {
    $game = Game::factory()->create(['code' => 'crash', 'slug' => 'crash']);
    $operator = Operator::factory()->create();
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id, 'settings' => ['play_mode' => 'relay']]);

    operatorApi($operator, 'POST', '/v1/games/crash/launch', ['player_external_id' => 'p-1', 'currency' => 'EUR'])
        ->assertStatus(403)
        ->assertJsonPath('error', 'direct_play_not_enabled');
    expect(LaunchToken::count())->toBe(0);
});
