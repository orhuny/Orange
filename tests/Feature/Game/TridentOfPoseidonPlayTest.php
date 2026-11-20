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

/** Trident of Poseidon, the second slot: its bets, verification, max win setting and page. */
beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

/**
 * @param  array<string, mixed>  $config
 * @return array{0: Operator, 1: Game}
 */
function poseidonSetup(array $config = [], float $rtp = 0.965): array
{
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '50.00'], 200)]);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create([
        'code' => 'trident-of-poseidon', 'slug' => 'trident-of-poseidon', 'type' => 'slot', 'access' => GameAccess::Listed,
        'rtp_target' => $rtp, 'house_edge' => 1 - $rtp,
        'config' => $config + ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'theme' => 'trident-of-poseidon'],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.20', 'max_bet' => '500', 'max_win' => '100000']);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'trident-seed-0009', 'server_seed_hash' => hash('sha256', 'trident-seed-0009'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);

    return [$operator, $game];
}

/** @return TestResponse<Response> */
function poseidonBet(Operator $operator, string $amount, string $key, string $mode = 'base'): TestResponse
{
    $json = (string) json_encode(['player_external_id' => 'p-1', 'amount' => $amount, 'currency' => 'EUR', 'params' => ['mode' => $mode]]);
    $timestamp = time();
    $path = '/v1/games/trident-of-poseidon/bets';

    return call('POST', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, 'POST', $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json);
}

it('plays a bought feature with the Tide, pins the max win, and verifies it exactly', function (): void {
    [$operator] = poseidonSetup();

    $played = poseidonBet($operator, '100.00', 'trident-buy', 'buy')->assertCreated()
        ->assertJsonPath('outcome.model', 'trident-of-poseidon')
        ->assertJsonPath('outcome.max_win_x', '5000.00')
        ->json('outcome');

    expect(Bet::sole()->params)->toBe(['mode' => 'buy', 'model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'max_win_x' => 5000, 'cost' => '100.00', 'rtp' => '0.9650'])
        ->and($played['free_spins'])->toBeGreaterThanOrEqual(15)
        ->and(array_key_exists('tide', $played['spins'][1]))->toBeTrue()
        ->and(array_key_exists('multiplier', $played['spins'][0]))->toBeTrue();

    $query = ['server_seed' => 'trident-seed-0009', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'buy', 'variant' => 'v1-9650'];
    $verified = get('/v1/fairness/trident-of-poseidon/verify?'.http_build_query($query + ['max_win_x' => $played['max_win_x']]))->assertOk()
        ->assertJsonPath('max_win_x', 5000)
        ->json();

    expect($verified['outcome'])->toBe($played);
    get('/v1/fairness/trident-of-poseidon/verify?'.http_build_query($query + ['max_win_x' => 7000]), ['Accept' => 'application/json'])->assertStatus(422);
});

it('publishes the pearls and the Tide in its rules', function (): void {
    poseidonSetup();

    get('/v1/fairness/trident-of-poseidon')->assertOk()
        ->assertJsonPath('multipliers', ['base_game' => true, 'free_spins' => 'tide'])
        ->assertJsonPath('free_spins.awarded', 15)
        ->assertJsonPath('max_win', '5000.00')
        ->assertJsonPath('tables.base_multipliers.100', 1)
        ->assertJsonPath('tables.multipliers.500', 1);
});

it('offers only the max wins its variant was measured with', function (): void {
    [, $game] = poseidonSetup();
    $engine = app(SlotEngine::class);

    expect($engine->validateConfig($game, ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'theme' => 'trident-of-poseidon'])['max_win_x'])->toBe(5000)
        ->and($engine->maxMultiplier($game))->toBe('5000.0000')
        ->and(fn () => $engine->validateConfig($game, ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'theme' => 'trident-of-poseidon', 'max_win_x' => 7000]))
        ->toThrow(InvalidGameConfig::class, 'max win can be')
        ->and(fn () => $engine->validateConfig($game, ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'max_win_x' => 10000]))
        ->toThrow(InvalidGameConfig::class, "set the game's RTP target to 0.9656")
        ->and(fn () => $engine->validateConfig($game, ['model' => 'trident-of-poseidon', 'variant' => 'v1-9650', 'theme' => 'sugar-orchard']))
        ->toThrow(InvalidGameConfig::class)
        // No theme picked: its own, not the first slot's.
        ->and($engine->validateConfig($game, ['model' => 'trident-of-poseidon'])['theme'])->toBe('trident-of-poseidon');
});

it('plays a game set to a 1,000× max win: the bet pins that cap and its RTP, and verifies with it', function (): void {
    [$operator] = poseidonSetup(['max_win_x' => 1000], 0.9169);

    $played = poseidonBet($operator, '100.00', 'trident-1000', 'buy')->assertCreated()
        ->assertJsonPath('outcome.max_win_x', '1000.00')
        ->json('outcome');

    expect(Bet::sole()->params)->toMatchArray(['max_win_x' => 1000, 'rtp' => '0.9169'])
        ->and((float) $played['total_win_x'])->toBeLessThanOrEqual(1000.0);

    $query = ['server_seed' => 'trident-seed-0009', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'buy', 'variant' => 'v1-9650', 'max_win_x' => 1000];
    expect(get('/v1/fairness/trident-of-poseidon/verify?'.http_build_query($query))->assertOk()->json('outcome'))->toBe($played);
});

it('serves its page in its own sea: the theme kit, sound, pearls, Poseidon and colours', function (): void {
    poseidonSetup();

    get('/games/slot?game=trident-of-poseidon')->assertOk()
        ->assertSee('slot-kit-tides.js', false)
        ->assertSee('kit: createKit', false)
        ->assertSee('sound-tides.js', false)
        ->assertSee('images/trident-of-poseidon/logo.webp?v=', false)
        ->assertSee('host/poseidon-idle.webp', false)
        ->assertSee('symbols\/pearl-abyss.webp', false)
        ->assertSee('"tiers":[[100,"abyss"]', false)
        ->assertSee('.tides-caustics', false)
        ->assertSee('Free spins about twice as often')
        ->assertDontSee('images/sugar-orchard', false);
});
