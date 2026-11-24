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

/** Tutti Frutti, the first paylines slot: its bets, its rules and its duet page. */
beforeEach(function (): void {
    Cache::flush();
    config()->set('wallet.callback.retry_backoff_ms', 1);
});

/**
 * @param  array<string, mixed>  $config
 * @return array{0: Operator, 1: Game}
 */
function tuttiSetup(array $config = [], float $rtp = 0.965): array
{
    Http::fake(['wallet.test/*' => Http::response(['balance_after' => '500.00'], 200)]);
    $operator = Operator::factory()->create(['callback_base_url' => 'https://wallet.test/w']);
    $game = Game::factory()->create([
        'code' => 'tutti-frutti', 'slug' => 'tutti-frutti', 'type' => 'slot', 'access' => GameAccess::Listed,
        'rtp_target' => $rtp, 'house_edge' => 1 - $rtp,
        'config' => $config + ['model' => 'tutti-frutti', 'variant' => 'v1-9650', 'theme' => 'tutti-frutti'],
    ]);
    OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.20', 'max_bet' => '500', 'max_win' => '100000']);
    $player = Player::factory()->create(['operator_id' => $operator->id, 'external_player_id' => 'p-1']);
    PlayerSeed::create([
        'operator_id' => $operator->id, 'player_id' => $player->id,
        'server_seed' => 'tutti-seed-0009', 'server_seed_hash' => hash('sha256', 'tutti-seed-0009'),
        'client_seed' => 'player-seed', 'nonce' => 0, 'active' => true,
    ]);

    return [$operator, $game];
}

/** @return TestResponse<Response> */
function tuttiBet(Operator $operator, string $amount, string $key, string $mode = 'base'): TestResponse
{
    $json = (string) json_encode(['player_external_id' => 'p-1', 'amount' => $amount, 'currency' => 'EUR', 'params' => ['mode' => $mode]]);
    $timestamp = time();
    $path = '/v1/games/tutti-frutti/bets';

    return call('POST', $path, [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id, 'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, 'POST', $path, $json, $timestamp, $key),
        'HTTP_X_SIGNATURE_VERSION' => '2', 'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $json);
}

it('plays a base spin on the lines, pins its maths to the bet, and verifies it exactly', function (): void {
    [$operator] = tuttiSetup();

    $played = tuttiBet($operator, '1.00', 'tutti-base')->assertCreated()
        ->assertJsonPath('outcome.model', 'tutti-frutti')
        ->assertJsonPath('outcome.mode', 'base')
        ->assertJsonPath('outcome.cost', '1.00')
        ->json('outcome');

    expect(Bet::sole()->params)->toBe(['mode' => 'base', 'model' => 'tutti-frutti', 'variant' => 'v1-9650', 'max_win_x' => 5000, 'cost' => '1.00', 'rtp' => '0.9650'])
        ->and(strlen($played['spins'][0]['grid']))->toBe(15)
        ->and($played['spins'][0])->toHaveKeys(['lines', 'wilds', 'line_win_x', 'scatters']);

    $query = ['server_seed' => 'tutti-seed-0009', 'client_seed' => 'player-seed', 'nonce' => 1, 'mode' => 'base', 'variant' => 'v1-9650', 'max_win_x' => 5000];
    get('/v1/fairness/tutti-frutti/verify?'.http_build_query($query))->assertOk()
        ->assertJsonPath('algorithm', 'hmac-sha256-stream-lines-slot')
        ->assertJsonPath('outcome', $played);
});

it('plays a bought feature at 100×: at least 10 free spins with the amore', function (): void {
    [$operator] = tuttiSetup();

    $played = tuttiBet($operator, '100.00', 'tutti-buy', 'buy')->assertCreated()->assertJsonPath('outcome.cost', '100.00')->json('outcome');

    expect($played['free_spins'])->toBeGreaterThanOrEqual(10)
        ->and($played['spins'][1])->toHaveKey('amore')
        ->and($played['spins'][1]['amore'])->toBeGreaterThanOrEqual(1)
        ->and($played['payout_multiplier'])->toBe(bcdiv(str_replace('.', '', $played['total_win_x']), '10000', 4));
});

it('has no ante and no super buy: both are refused before a stake is taken', function (string $mode): void {
    [$operator] = tuttiSetup();

    tuttiBet($operator, '1.25', "tutti-{$mode}", $mode)->assertStatus(422)->assertJsonPath('error', 'invalid_params');

    expect(Bet::count())->toBe(0);
    Http::assertNothingSent();
})->with(['ante', 'super']);

it('publishes its lines, paytable by count, wilds and free spins in its rules', function (): void {
    tuttiSetup();
    $rules = get('/v1/fairness/tutti-frutti')->assertOk()
        ->assertJsonPath('algorithm', 'hmac-sha256-stream-lines-slot')
        ->assertJsonPath('grid', ['columns' => 5, 'rows' => 3])
        ->assertJsonPath('costs', ['base' => '1.00', 'buy' => '100.00'])
        ->assertJsonPath('features', ['ante' => false, 'buy' => true])
        ->assertJsonPath('symbols.A', 'Peach')
        ->assertJsonPath('symbols.W', 'Kiss (wild)')
        ->assertJsonPath('free_spins.awarded_by_count', [3 => 10, 4 => 12, 5 => 15])
        ->assertJsonPath('wilds.base_game', [1, 2, 3, 5])
        ->json();

    expect($rules['lines'])->toHaveCount(20)
        ->and($rules['paytable'][0]['pays'])->toBe(['2' => '0.25', '3' => '2.50', '4' => '10.00', '5' => '50.00'])
        ->and($rules['paytable'][0]['line_pays'])->toBe(['2' => '5', '3' => '50', '4' => '200', '5' => '1000'])
        ->and($rules['formula'])->toContain('the amore starts at 1')
        ->and($rules['tables'])->toHaveKeys(['base', 'buy', 'free', 'base_wilds', 'free_wilds', 'buy_scatters']);
});

it('offers every measured max win, each with its RTP, and rejects any other', function (): void {
    $game = tuttiSetup(['max_win_x' => 20000], 0.9651)[1];

    expect(app(SlotEngine::class)->validateConfig($game, (array) $game->config)['max_win_x'])->toBe(20000);
    expect(fn () => app(SlotEngine::class)->validateConfig($game, ['model' => 'tutti-frutti', 'variant' => 'v1-9650', 'max_win_x' => 7777]))
        ->toThrow(InvalidGameConfig::class);
});

it('serves its page in the duet layout: the reels, the girls and their picker, Amore, the kit and the sound', function (): void {
    tuttiSetup();
    get('/games/slot?game=tutti-frutti')->assertOk()
        ->assertSee('<body class="layout-duet">', false)
        ->assertSee('<aside class="duet left"', false)
        ->assertSee('<aside class="duet right"', false)
        // The girl picker: all 10 girls, Bella and Marina by default, the picker's button.
        ->assertSee('host\/marina-idle.webp', false)
        ->assertSee('host\/bella-kiss.webp', false)
        ->assertSee('host\/luna-tease.webp', false)
        ->assertSee('cards\/perla.webp', false)
        ->assertSee('"pair":{"right":"bella","left":"marina"}', false)
        ->assertSee('id="girls-btn"', false)
        ->assertSee('data-host="right"', false)
        ->assertSee('id="amore"', false)
        ->assertSee('slot-kit-riviera.js', false)
        ->assertSee('sound-riviera.js', false)
        ->assertSee('"lines":[[1,1,1,1,1]', false)
        ->assertSee('symbols\/kiss-gold.webp', false)
        ->assertDontSee('class="tumble"', false);
})->skip(fn (): bool => ! is_file(public_path('images/tutti-frutti/art.json')), 'Tutti Frutti\'s art is not built.');
