<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\SlotEngine;
use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Exceptions\InvalidGameConfig;
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
use App\Domain\Wallet\Models\WalletTransaction;
use App\Domain\Wallet\Models\Webhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Expected outcomes come from the independent reference (tools/slot-reference/reference.py)
 * for seed pair orange-slot-seed-0005 / player-seed, variant v1-9650:
 *   nonce 1: base 7.85× (three tumbles) · ante 1.65× · buy 73.80× with 10 free spins
 *   nonce 2: base 0.00×
 */

beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

/**
 * @param  array<string, mixed>  $config
 * @return array{0: Operator, 1: Game, 2: Player}
 */
function slotSetup(string $maxWin = '100000', bool $fakeWallet = true, array $config = []): array
{
    if ($fakeWallet) {
        Http::fake(['wallet.test/*' => Http::response(['balance_after' => '50.00'], 200)]);
    }

    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create([
        'code' => 'sugar-orchard', 'slug' => 'sugar-orchard', 'type' => 'slot', 'access' => GameAccess::Listed,
        'rtp_target' => 0.965, 'house_edge' => 0.035, 'config' => $config + ['model' => 'sugar-orchard', 'variant' => 'v1-9650'],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.20', 'max_bet' => '500', 'max_win' => $maxWin]);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'orange-slot-seed-0005', 'server_seed_hash' => hash('sha256', 'orange-slot-seed-0005'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);

    return [$operator, $game, $player];
}

/** @return TestResponse<Response> */
function spin(Operator $operator, string $amount, string $key, ?string $mode = null): TestResponse
{
    $body = ['player_external_id' => 'p-1', 'amount' => $amount, 'currency' => 'EUR', 'params' => $mode === null ? [] : ['mode' => $mode]];
    $json = (string) json_encode($body);
    $timestamp = time();
    $path = '/v1/games/sugar-orchard/bets';

    return call('POST', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, 'POST', $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json);
}

it('takes the stake, spins, pays the tumbles and reports how to verify it', function (): void {
    [$operator] = slotSetup();

    $response = spin($operator, '2.00', 'slot-1')->assertCreated();

    $bet = Bet::sole();
    $response->assertJsonPath('status', 'won')
        ->assertJsonPath('outcome.mode', 'base')
        ->assertJsonPath('outcome.variant', 'v1-9650')
        ->assertJsonPath('outcome.spins.0.grid', 'EEEIFESGGHGGEDBFIIHGGGIDHGHIEF')
        ->assertJsonCount(3, 'outcome.spins.0.tumbles')
        ->assertJsonPath('outcome.total_win_x', '7.85')
        ->assertJsonPath('outcome.payout_multiplier', '7.8500')
        ->assertJsonPath('multiplier', '7.8500')
        ->assertJsonPath('payout_amount', '15.70000000')
        ->assertJsonPath('fairness.nonce', 1);
    expect($bet->params)->toBe(['mode' => 'base', 'model' => 'sugar-orchard', 'variant' => 'v1-9650', 'cost' => '1.00', 'rtp' => '0.9650']);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/debit') && $r['tx_id'] === 'bet:slot-1' && bccomp((string) $r['amount'], '2', 8) === 0);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit') && $r['tx_id'] === "payout:{$bet->id}" && $r['amount'] === '15.70000000');
    expect(data_get(Webhook::query()->where('event', 'bet.settled')->sole()->payload, 'outcome.total_win_x'))->toBe('7.85');
});

it('pays nothing on a losing spin, and moves to the next nonce', function (): void {
    [$operator] = slotSetup();
    spin($operator, '2.00', 'slot-1')->assertCreated();

    spin($operator, '2.00', 'slot-2')->assertCreated()
        ->assertJsonPath('status', 'lost')
        ->assertJsonPath('outcome.spins.0.grid', 'CBICEDCIDIHBGGFBFDFHBBECGCEFFI')
        ->assertJsonPath('payout_amount', '0.00000000')
        ->assertJsonPath('fairness.nonce', 2);

    expect(WalletTransaction::query()->where('type', 'credit')->count())->toBe(1);
});

it('prices the ante bet at 1.25× and pays wins on the bet, not the stake', function (): void {
    [$operator] = slotSetup();

    // A 2.00 bet with the ante costs 2.50; 1.65× the bet pays 3.30 (= 2.50 × 1.32).
    spin($operator, '2.50', 'slot-ante', 'ante')->assertCreated()
        ->assertJsonPath('outcome.mode', 'ante')
        ->assertJsonPath('outcome.cost', '1.25')
        ->assertJsonPath('outcome.total_win_x', '1.65')
        ->assertJsonPath('multiplier', '1.3200')
        ->assertJsonPath('payout_amount', '3.30000000');
});

it('sells the feature at 100× and plays the free spins in the same bet', function (): void {
    [$operator] = slotSetup();

    // A 1.00 bet: the buy costs 100.00 and 73.80× the bet pays 73.80.
    $response = spin($operator, '100.00', 'slot-buy', 'buy')->assertCreated()
        ->assertJsonPath('outcome.mode', 'buy')
        ->assertJsonPath('outcome.free_spins', 10)
        ->assertJsonPath('outcome.total_win_x', '73.80')
        ->assertJsonPath('multiplier', '0.7380')
        ->assertJsonPath('payout_amount', '73.80000000');

    expect($response->json('outcome.spins'))->toHaveCount(11)
        ->and(substr_count((string) $response->json('outcome.spins.0.grid'), 'S'))->toBeGreaterThanOrEqual(4);
});

it('caps a win at the max win', function (): void {
    [$operator] = slotSetup(maxWin: '5.00');

    spin($operator, '2.00', 'slot-cap')->assertCreated()->assertJsonPath('payout_amount', '5.00000000');
});

it('never spins for a stake the operator refused, or one it did not answer', function (): void {
    Http::fake(['wallet.test/w/wallet/debit' => Http::sequence()
        ->push(['error' => 'insufficient_funds'], 402)
        ->push(['error' => 'down'], 503),
        'wallet.test/*' => Http::response(['ok' => true], 200)]);
    [$operator] = slotSetup(fakeWallet: false);

    spin($operator, '2.00', 'slot-refused')->assertStatus(402);
    spin($operator, '2.00', 'slot-unanswered')->assertStatus(503);

    $bets = Bet::query()->orderBy('id')->get();
    expect($bets->pluck('status')->all())->toBe([BetStatus::Rejected, BetStatus::RolledBack])
        ->and($bets->pluck('outcome')->filter()->all())->toBe([]);
    Http::assertNotSent(fn (Request $r): bool => str_ends_with($r->url(), '/wallet/credit'));
});

it('refuses bad modes and switched-off features before taking a nonce or a stake', function (string $mode, array $config): void {
    [$operator] = slotSetup(config: $config);

    spin($operator, '2.00', 'slot-bad', $mode)->assertStatus(422)->assertJsonPath('error', 'invalid_params');

    expect(Bet::count())->toBe(0)
        ->and(PlayerSeed::sole()->nonce)->toBe(0);
    Http::assertNothingSent();
})->with([
    'unknown mode' => ['turbo', []],
    'ante switched off' => ['ante', ['ante' => false]],
    'buy switched off' => ['buy', ['buy' => false]],
]);

it('verifies a spin from the revealed seed exactly as it was played', function (): void {
    [$operator] = slotSetup();
    $played = spin($operator, '100.00', 'slot-buy', 'buy')->assertCreated()->json('outcome');

    $verified = get('/v1/fairness/sugar-orchard/verify?'.http_build_query([
        'server_seed' => 'orange-slot-seed-0005', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'buy', 'variant' => 'v1-9650',
    ]))->assertOk()
        ->assertJsonPath('total_win_x', '73.80')
        ->assertJsonPath('free_spins', 10)
        ->assertJsonPath('payout_multiplier', '0.7380')
        ->json();

    expect($verified['outcome'])->toBe($played);
    get('/v1/fairness/sugar-orchard/verify?'.http_build_query([
        'server_seed' => 'orange-slot-seed-0005', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'buy', 'variant' => 'v9-nope',
    ]), ['Accept' => 'application/json'])->assertStatus(422);
});

it('publishes the paytable, rules and every weight table', function (): void {
    slotSetup();

    get('/v1/fairness/sugar-orchard')->assertOk()
        ->assertJsonPath('algorithm', 'hmac-sha256-stream-tumble-slot')
        ->assertJsonPath('rtp', '0.9650')
        ->assertJsonPath('paytable.0.pays', ['8-9' => '15.00', '10-11' => '37.50', '12+' => '75.00'])
        ->assertJsonPath('scatter_pays', ['4' => '2.50', '5' => '8.00', '6' => '60.00'])
        ->assertJsonPath('max_win', '5000.00')
        ->assertJsonPath('costs', ['base' => '1.00', 'ante' => '1.25', 'buy' => '100.00'])
        ->assertJsonPath('tables.base.0.S', 185)
        ->assertJsonPath('tables.multipliers.100', 3);
});

it('plays a slot in direct play through the player API', function (): void {
    [$operator, $game] = slotSetup();
    OperatorGame::query()->where('operator_id', $operator->id)->where('game_id', $game->id)->update(['settings' => json_encode(['play_mode' => 'direct'])]);
    [, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);
    $auth = ['Authorization' => "Bearer {$bearer}"];

    postJson('/play-api/games/sugar-orchard/bets', ['amount' => '1.00', 'params' => ['mode' => 'base']], $auth + ['Idempotency-Key' => 'page-key-slot-01'])
        ->assertCreated()
        ->assertJsonPath('outcome.total_win_x', '7.85')
        ->assertJsonPath('payout_amount', '7.85000000')
        ->assertJsonPath('player_balance', '50.00');
    getJson('/play-api/games/sugar-orchard/bets', $auth)->assertOk()->assertJsonCount(1, 'bets');
});

it('lists the player\'s slot bets as a game history, with each bet\'s details and seeds', function (): void {
    [$operator, $game] = slotSetup();
    OperatorGame::query()->where('operator_id', $operator->id)->where('game_id', $game->id)->update(['settings' => json_encode(['play_mode' => 'direct'])]);
    [, $bearer] = app(PlaySessionService::class)->exchange(app(LaunchService::class)->issue($operator, $game, 'p-1', 'EUR')->token);
    $auth = ['Authorization' => "Bearer {$bearer}"];
    postJson('/play-api/games/sugar-orchard/bets', ['amount' => '1.00', 'params' => ['mode' => 'base']], $auth + ['Idempotency-Key' => 'page-key-slot-01'])->assertCreated();
    postJson('/play-api/games/sugar-orchard/bets', ['amount' => '1.25', 'params' => ['mode' => 'ante']], $auth + ['Idempotency-Key' => 'page-key-slot-02'])->assertCreated();

    $history = getJson('/play-api/games/sugar-orchard/bets', $auth)->assertOk()
        // Newest first: the ante bet, then the 7.85× base spin.
        ->assertJsonPath('bets.0.game_type', 'slot')
        ->assertJsonPath('bets.0.details.mode', 'ante')
        ->assertJsonPath('bets.0.details.bet', '1.00')
        ->assertJsonPath('bets.1.details', ['mode' => 'base', 'bet' => '1.00', 'win_x' => '7.85', 'free_spins' => 0, 'max_win' => false])
        ->assertJsonPath('bets.1.payout_amount', '7.85000000')
        ->assertJsonPath('bets.1.fairness.nonce', 1)
        ->assertJsonPath('bets.0.fairness.nonce', 2);

    // The hash commits to the server seed; the seed itself stays secret until it is rotated.
    $seed = PlayerSeed::query()->sole();
    expect($history->json('bets.1.fairness.server_seed_hash'))->toBe($seed->server_seed_hash)
        ->and($history->getContent())->not->toContain($seed->server_seed);
});

it('keeps the RTP target and the variant in step', function (): void {
    $engine = app(SlotEngine::class);
    $game = new Game(['type' => 'slot', 'rtp_target' => 0.955, 'house_edge' => 0.045]);

    expect($engine->validateConfig($game, ['variant' => 'v1-9550']))->toBe([
        'model' => 'sugar-orchard', 'variant' => 'v1-9550', 'theme' => 'sugar-orchard', 'ante' => true, 'buy' => true, 'autoplay' => true,
        'bet_steps' => ['0.20', '0.40', '0.60', '0.80', '1.00', '2.00', '3.00', '4.00', '5.00', '10.00', '20.00', '50.00', '100.00'],
    ])
        ->and(fn () => $engine->validateConfig($game, ['variant' => 'v1-9650']))->toThrow(InvalidGameConfig::class, 'set the game\'s RTP target to 0.9650')
        ->and(fn () => $engine->validateConfig($game, ['model' => 'nope']))->toThrow(InvalidGameConfig::class)
        ->and(fn () => $engine->validateConfig($game, ['variant' => 'v1-9550', 'theme' => 'nope']))->toThrow(InvalidGameConfig::class)
        ->and(fn () => $engine->validateConfig($game, ['variant' => 'v1-9550', 'bet_steps' => ['1', '-2']]))->toThrow(InvalidGameConfig::class)
        ->and($engine->validateConfig($game, ['variant' => 'v1-9550', 'bet_steps' => '5, 1, 1.5'])['bet_steps'])->toBe(['1.00', '1.50', '5.00'])
        ->and($engine->maxMultiplier($game->forceFill(['config' => ['variant' => 'v1-9550']])))->toBe('5000.0000');
});

it('serves the slot page with its theme, paytable and the shared bridge', function (): void {
    slotSetup(config: ['buy' => false]);

    get('/games/slot?game=sugar-orchard')->assertOk()
        ->assertSee('slot-game.js', false)
        ->assertSee('bridge.js', false)
        ->assertSee('symbols\/toffee.webp', false)
        ->assertSee('"paytable":', false)
        ->assertSee('"features":{"ante":true,"buy":false}', false)
        ->assertDontSee('"tables":', false);
    get('/games/slot?game=nope')->assertNotFound();
});

it('dresses the slot page in its built art: frame, stars, explosion pieces and effects', function (): void {
    slotSetup();

    // From public/images/sugar-orchard/art.json (scripts/art/process-sugar-orchard.py).
    get('/games/slot?game=sugar-orchard')->assertOk()
        ->assertSee('images/sugar-orchard/frame.webp?v=', false)
        ->assertSee('images/sugar-orchard/spin.webp?v=', false)
        ->assertSee('--kx:', false)
        ->assertSee('symbols\/basket.webp', false)
        ->assertSee('symbols\/star-ruby.webp', false)
        ->assertSee('frag\/a-1.webp', false)
        ->assertSee('fx\/frost-burst.webp', false)
        ->assertSee('banners\/big-win.webp', false)
        ->assertDontSee('gumball-machine', false);
});
