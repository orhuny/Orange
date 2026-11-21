<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\SlotEngine;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\call;
use function Pest\Laravel\get;

/** Stones of the Titan, the third slot: its Super Eclipse buy, its rules and its throne page. */
beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

/**
 * @param  array<string, mixed>  $config
 * @return array{0: Operator, 1: Game}
 */
function titanSetup(array $config = [], float $rtp = 0.965, string $code = 'stones-of-the-titan'): array
{
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '500.00'], 200)]);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create([
        'code' => $code, 'slug' => $code, 'type' => 'slot', 'access' => GameAccess::Listed,
        'rtp_target' => $rtp, 'house_edge' => 1 - $rtp,
        'config' => $config + ['model' => $code, 'variant' => 'v1-9650', 'theme' => $code],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.20', 'max_bet' => '500', 'max_win' => '100000']);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'titan-seed-0009', 'server_seed_hash' => hash('sha256', 'titan-seed-0009'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);

    return [$operator, $game];
}

/** @return TestResponse<Response> */
function titanBet(Operator $operator, string $amount, string $key, string $mode = 'base', string $code = 'stones-of-the-titan'): TestResponse
{
    $json = (string) json_encode(['player_external_id' => 'p-1', 'amount' => $amount, 'currency' => 'EUR', 'params' => ['mode' => $mode]]);
    $timestamp = time();
    $path = "/v1/games/{$code}/bets";

    return call('POST', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, 'POST', $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json);
}

it('plays a Super Eclipse buy: priced per variant, the Eclipse starting at 10, and verifies it exactly', function (): void {
    [$operator] = titanSetup();

    $played = titanBet($operator, '179.00', 'titan-super', 'super')->assertCreated()
        ->assertJsonPath('outcome.model', 'stones-of-the-titan')
        ->assertJsonPath('outcome.mode', 'super')
        ->assertJsonPath('outcome.tide_start', 10)
        ->assertJsonPath('outcome.cost', '179.00')
        ->json('outcome');

    expect(Bet::sole()->params)->toBe(['mode' => 'super', 'model' => 'stones-of-the-titan', 'variant' => 'v1-9650', 'max_win_x' => 5000, 'cost' => '179.00', 'rtp' => '0.9650'])
        ->and($played['free_spins'])->toBeGreaterThanOrEqual(15)
        ->and($played['spins'][1]['tide'])->toBeGreaterThanOrEqual(10)
        // Paid on the full stake: multiplier = win ÷ 179.
        ->and($played['payout_multiplier'])->toBe(bcdiv(str_replace('.', '', $played['total_win_x']), '17900', 4));

    $query = ['server_seed' => 'titan-seed-0009', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'super', 'variant' => 'v1-9650', 'max_win_x' => 5000];
    expect(get('/v1/fairness/stones-of-the-titan/verify?'.http_build_query($query))->assertOk()->json('outcome'))->toBe($played);
});

it('refuses a super buy where it is switched off, and on a slot without one, before taking a stake', function (string $code, array $config): void {
    [$operator] = titanSetup($config, code: $code);

    titanBet($operator, '179.00', 'titan-no-super', 'super', $code)->assertStatus(422)->assertJsonPath('error', 'invalid_params');

    expect(Bet::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'switched off' => ['stones-of-the-titan', ['super' => false]],
    'Trident has none' => ['trident-of-poseidon', []],
]);

it('refuses to verify a super buy for a slot without one', function (): void {
    titanSetup(code: 'trident-of-poseidon');

    get('/v1/fairness/trident-of-poseidon/verify?'.http_build_query([
        'server_seed' => 'titan-seed-0009', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'super',
    ]), ['Accept' => 'application/json'])->assertStatus(422);
});

it('needs the default max win or more for the super buy', function (): void {
    [, $game] = titanSetup();
    $engine = app(SlotEngine::class);
    $base = ['model' => 'stones-of-the-titan', 'variant' => 'v1-9650', 'theme' => 'stones-of-the-titan'];
    $game->rtp_target = 0.9511;

    expect(fn () => $engine->validateConfig($game, $base + ['max_win_x' => 2500]))
        ->toThrow(InvalidGameConfig::class, 'The super buy needs a max win of at least 5,000×')
        ->and($engine->validateConfig($game, $base + ['max_win_x' => 2500, 'super' => false])['super'])->toBeFalse();

    $game->rtp_target = 0.9657;
    expect($engine->validateConfig($game, $base + ['max_win_x' => 20000])['super'])->toBeTrue()
        // A slot without a super buy keeps its config as it was: no super key at all.
        ->and($engine->validateConfig($game, ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'max_win_x' => 20000]))->not->toHaveKey('super');
});

it('publishes the super buy, its price and where the Eclipse starts in its rules', function (): void {
    titanSetup();

    $rules = get('/v1/fairness/stones-of-the-titan')->assertOk()
        ->assertJsonPath('costs', ['base' => '1.00', 'ante' => '1.25', 'buy' => '100.00', 'super' => '179.00'])
        ->assertJsonPath('features.super', true)
        ->assertJsonPath('free_spins.super_buy_tide', 10)
        ->assertJsonPath('multipliers', ['base_game' => true, 'free_spins' => 'tide'])
        ->assertJsonPath('symbols.A', 'Caged star')
        ->json();

    expect($rules['formula'])->toContain('A super buy is a bought spin whose free spins start with the tide at 10')
        ->toContain('100 (buy) or 179.00 (super)')
        ->and($rules['inputs']['mode'])->toContain('super');
});

it('prices the super buy with the variant the game plays', function (): void {
    [$operator] = titanSetup(['variant' => 'v1-9450'], 0.945);

    titanBet($operator, '180.00', 'titan-9450', 'super')->assertCreated()->assertJsonPath('outcome.cost', '180.00');
    get('/v1/fairness/stones-of-the-titan')->assertJsonPath('costs.super', '180.00');
});

it('serves its page in the throne layout: the kit, sound, cards, plaque, meteors and the Titan', function (): void {
    titanSetup();

    get('/games/slot?game=stones-of-the-titan')->assertOk()
        ->assertSee('<body class="layout-throne">', false)
        ->assertSee('class="tumble"', false)
        ->assertSee('id="super-box"', false)
        ->assertSee('Super Eclipse')
        ->assertSee('<aside class="throne">', false)
        ->assertSee('host/titan-idle.webp', false)
        ->assertSee('class="pedestal"', false)
        ->assertSee('slot-kit-cosmos.js', false)
        ->assertSee('sound-cosmos.js', false)
        ->assertSee('symbols\/meteor-sun.webp', false)
        ->assertSee('"tiers":[[100,"sun"]', false)
        ->assertSee('"super":true', false)
        ->assertSee('.eclipse', false)
        ->assertSee('--fy:', false)
        ->assertDontSee('images/trident-of-poseidon', false);
});

it('keeps the other slots on the classic layout, with no super buy', function (): void {
    titanSetup(code: 'trident-of-poseidon');

    get('/games/slot?game=trident-of-poseidon')->assertOk()
        ->assertSee('<body class="layout-classic">', false)
        ->assertDontSee('id="super-box"', false)
        ->assertDontSee('class="tumble"', false)
        ->assertDontSee('<aside class="throne">', false)
        ->assertDontSee('--fy:', false);
});
