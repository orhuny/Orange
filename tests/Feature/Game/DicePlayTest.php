<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Launch\Services\LaunchService;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Models\WalletTransaction;
use App\Domain\Wallet\Models\Webhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\artisan;
use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

/** @return array{0: Operator, 1: Game, 2: Player} */
function diceSetup(string $maxWin = '10000', bool $fakeWallet = true): array
{
    // (A test that needs other wallet answers fakes them itself, before this: a second
    // Http::fake() would only add stubs behind the first.)
    if ($fakeWallet) {
        Http::fake(['wallet.test/*' => Http::response(['balance_after' => '50.00'], 200)]);
    }

    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create([
        'code' => 'dice', 'slug' => 'dice', 'type' => 'dice', 'access' => GameAccess::Listed,
        'rtp_target' => 0.99, 'house_edge' => 0.01, 'config' => ['min_chance' => '0.01', 'max_chance' => '98.00'],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.10', 'max_bet' => '100', 'max_win' => $maxWin]);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    // A known seed pair, so the rolls are the published golden values: nonce 1 → 44.30, nonce 2 → 44.41.
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'orange-dice-seed-0001', 'server_seed_hash' => hash('sha256', 'orange-dice-seed-0001'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);

    return [$operator, $game, $player];
}

/**
 * @param  array<string, mixed>|null  $body
 * @return TestResponse<Response>
 */
function diceApi(Operator $operator, string $method, string $path, ?array $body, string $key): TestResponse
{
    $json = $body === null ? '' : (string) json_encode($body);
    $timestamp = time();

    return call($method, $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, $method, $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json);
}

/** @return TestResponse<Response> */
function roll(Operator $operator, string $target, string $direction, string $key, string $amount = '2.00'): TestResponse
{
    return diceApi($operator, 'POST', '/v1/games/dice/bets', [
        'player_external_id' => 'p-1', 'amount' => $amount, 'currency' => 'EUR',
        'params' => ['target' => $target, 'direction' => $direction],
    ], $key);
}

it('takes the stake, rolls, pays a win and reports how to verify it', function (): void {
    [$operator] = diceSetup();

    $response = roll($operator, '50', 'under', 'dice-1')->assertCreated();

    $bet = Bet::sole();
    $response->assertJsonPath('status', 'won')
        ->assertJsonPath('outcome.roll', '44.30')
        ->assertJsonPath('outcome.win', true)
        ->assertJsonPath('multiplier', '1.9800')
        ->assertJsonPath('payout_amount', '3.96000000')
        ->assertJsonPath('fairness', ['server_seed_hash' => hash('sha256', 'orange-dice-seed-0001'), 'client_seed' => 'player-seed', 'nonce' => 1]);
    expect($bet->status)->toBe(BetStatus::Won)
        ->and($bet->round_id)->toBeNull()
        ->and($bet->nonce)->toBe(1);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/debit') && $r['tx_id'] === 'bet:dice-1');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit') && $r['tx_id'] === "payout:{$bet->id}" && $r['amount'] === '3.96000000');
    $webhook = Webhook::query()->where('event', 'bet.settled')->sole();
    expect(data_get($webhook->payload, 'outcome.roll'))->toBe('44.30')
        ->and(data_get($webhook->payload, 'payout_amount'))->toBe('3.96000000');
});

it('pays nothing on a loss, and moves to the next nonce', function (): void {
    [$operator] = diceSetup();
    roll($operator, '50', 'under', 'dice-1')->assertCreated();

    roll($operator, '50', 'over', 'dice-2')->assertCreated()
        ->assertJsonPath('status', 'lost')
        ->assertJsonPath('outcome.roll', '44.41')
        ->assertJsonPath('payout_amount', '0.00000000')
        ->assertJsonPath('fairness.nonce', 2);

    expect(WalletTransaction::query()->where('type', 'credit')->count())->toBe(1);
});

it('caps a win at the max win', function (): void {
    [$operator] = diceSetup(maxWin: '3.00');

    roll($operator, '50', 'under', 'dice-cap')->assertCreated()->assertJsonPath('payout_amount', '3.00000000');
});

it('never rolls for a stake the operator refused, or one it did not answer', function (): void {
    Http::fake(['wallet.test/w/wallet/debit' => Http::sequence()
        ->push(['error' => 'insufficient_funds'], 402)
        ->push(['error' => 'down'], 503),
        'wallet.test/*' => Http::response(['ok' => true], 200)]);
    [$operator] = diceSetup(fakeWallet: false);

    roll($operator, '50', 'under', 'dice-refused')->assertStatus(402)->assertJsonPath('reason', 'insufficient_funds');
    roll($operator, '50', 'under', 'dice-unanswered')->assertStatus(503);

    $bets = Bet::query()->orderBy('id')->get();
    expect($bets->pluck('status')->all())->toBe([BetStatus::Rejected, BetStatus::RolledBack])
        ->and($bets->pluck('outcome')->filter()->all())->toBe([])
        ->and(Webhook::query()->where('event', 'bet.settled')->count())->toBe(0);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/rollback') && $r['tx_id'] === "rollback:{$bets[1]->id}");
    Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit'));
});

it('refuses bad params before taking a nonce or a stake', function (): void {
    [$operator] = diceSetup();

    roll($operator, '99.50', 'under', 'dice-bad')->assertStatus(422)->assertJsonPath('error', 'invalid_params');

    expect(Bet::count())->toBe(0)
        ->and(PlayerSeed::sole()->nonce)->toBe(0);
    Http::assertNothingSent();
});

it('rotates the seed pair, revealing a server seed that verifies every bet on it', function (): void {
    [$operator] = diceSetup();
    roll($operator, '50', 'under', 'dice-1')->assertCreated();

    diceApi($operator, 'GET', '/v1/players/p-1/seeds', null, 'seeds-1')->assertOk()
        ->assertJsonPath('server_seed_hash', hash('sha256', 'orange-dice-seed-0001'))
        ->assertJsonPath('nonce', 1)
        ->assertJsonMissingPath('server_seed');

    $rotated = diceApi($operator, 'POST', '/v1/players/p-1/seeds/rotate', ['client_seed' => 'my-new-seed'], 'rotate-1')->assertOk();
    $rotated->assertJsonPath('previous.server_seed', 'orange-dice-seed-0001')
        ->assertJsonPath('previous.last_nonce', 1)
        ->assertJsonPath('current.client_seed', 'my-new-seed')
        ->assertJsonPath('current.nonce', 0);

    get('/v1/fairness/dice/verify?'.http_build_query([
        'server_seed' => $rotated->json('previous.server_seed'), 'client_seed' => 'player-seed', 'nonce' => 1,
        'target' => '50', 'direction' => 'under', 'expected_server_seed_hash' => hash('sha256', 'orange-dice-seed-0001'),
    ]))->assertOk()
        ->assertJsonPath('roll', '44.30')
        ->assertJsonPath('win', true)
        ->assertJsonPath('expected_hash_matches', true);
});

it('refuses to rotate (and so reveal) a seed pair while a bet on it is unresolved', function (): void {
    [$operator, $game, $player] = diceSetup();
    Bet::factory()->create(['round_id' => null, 'game_id' => $game->id, 'operator_id' => $operator->id, 'player_id' => $player->id,
        'player_seed_id' => PlayerSeed::sole()->id, 'nonce' => 1, 'status' => BetStatus::Pending]);

    diceApi($operator, 'POST', '/v1/players/p-1/seeds/rotate', [], 'rotate-busy')->assertStatus(409)->assertJsonPath('error', 'bets_in_progress');

    expect(PlayerSeed::sole()->isRevealed())->toBeFalse();
});

it('plays dice in direct play through the player API', function (): void {
    [$operator, $game] = diceSetup();
    [, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);
    $auth = ['Authorization' => "Bearer {$bearer}"];

    postJson('/play-api/games/dice/bets', ['amount' => '1.00', 'params' => ['target' => '50', 'direction' => 'under']], $auth + ['Idempotency-Key' => 'page-key-0001'])
        ->assertCreated()
        ->assertJsonPath('outcome.roll', '44.30')
        ->assertJsonPath('player_balance', '50.00');
    getJson('/play-api/seeds', $auth)->assertOk()->assertJsonPath('nonce', 1);
    postJson('/play-api/seeds/rotate', [], $auth)->assertOk()->assertJsonPath('previous.server_seed', 'orange-dice-seed-0001');
});

it('settles a bet a dying process left pending if its stake was taken, and rolls it back otherwise', function (): void {
    [$operator, $game, $player] = diceSetup();
    $seed = PlayerSeed::sole();
    $make = fn (int $nonce, string $key) => Bet::factory()->create([
        'round_id' => null, 'game_id' => $game->id, 'operator_id' => $operator->id, 'player_id' => $player->id,
        'player_seed_id' => $seed->id, 'nonce' => $nonce, 'status' => BetStatus::Pending, 'amount' => '2.00000000',
        'params' => ['target' => '50.00', 'direction' => 'under'], 'idempotency_key' => $key, 'placed_at' => now()->subMinutes(5),
    ]);
    $taken = $make(1, 'stale-taken');
    $unknown = $make(2, 'stale-unknown');
    WalletTransaction::factory()->create(['operator_id' => $operator->id, 'player_id' => $player->id, 'bet_id' => $taken->id,
        'type' => 'debit', 'amount' => '2.00', 'idempotency_key' => 'bet:stale-taken', 'operator_callback_status' => WalletCallbackStatus::Completed]);

    artisan('games:settle-stale-bets')->expectsOutputToContain('2 bet(s) handled')->assertSuccessful();

    expect($taken->fresh()->status)->toBe(BetStatus::Won)
        ->and(data_get($taken->fresh()->outcome, 'roll'))->toBe('44.30')
        ->and($unknown->fresh()->status)->toBe(BetStatus::RolledBack)
        ->and($unknown->fresh()->outcome)->toBeNull();
    Http::assertSent(fn (Request $r): bool => ($r->data()['tx_id'] ?? null) === "payout:{$taken->id}");
    Http::assertSent(fn (Request $r): bool => ($r->data()['tx_id'] ?? null) === "rollback:{$unknown->id}");
});

it('serves the dice page with the game\'s odds and the shared bridge', function (): void {
    diceSetup();

    get('/games/dice')->assertOk()
        ->assertSee('bridge.js', false)
        ->assertSee('const RTP = "0.9900"', false)
        ->assertSee('const MAX_CHANCE = "98.00"', false);
});
