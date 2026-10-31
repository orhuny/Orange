<?php

declare(strict_types=1);

use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\Wallet\Models\WalletTransaction;
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
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function limitedBet(Operator $operator, array $payload, string $key): TestResponse
{
    $body = (string) json_encode($payload);
    $timestamp = time();

    return call('POST', '/v1/bets', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_OPERATOR_ID' => $operator->id,
        'HTTP_X_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_SIGNATURE' => OperatorSignature::compute($operator->secret_key, 'POST', '/v1/bets', $body, $timestamp),
        'HTTP_IDEMPOTENCY_KEY' => $key,
    ], $body);
}

function limitedRound(Game $game): GameRound
{
    $orchestrator = app(RoundOrchestrator::class);

    return $orchestrator->openBetting($orchestrator->create($game));
}

/** @return array<string, mixed> */
function stake(GameRound $round, string $amount, string $currency = 'EUR'): array
{
    return ['round_uuid' => $round->round_uuid, 'player_external_id' => 'p-1', 'amount' => $amount, 'currency' => $currency];
}

function expectNothingTaken(): void
{
    expect(Bet::count())->toBe(0)
        ->and(WalletTransaction::count())->toBe(0);
    Http::assertNothingSent();
}

it('rejects stakes outside the per-currency limits with a 4xx and takes nothing', function (string $amount, string $error, string $limit): void {
    $game = Game::factory()->create();
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.5', 'max_bet' => '20', 'max_win' => '1000']);

    limitedBet(Operator::factory()->create(), stake(limitedRound($game), $amount), 'k-'.$error)
        ->assertStatus(422)
        ->assertJsonPath('error', $error)
        ->assertJsonPath('limit', $limit);

    expectNothingTaken();
})->with([
    'below min' => ['0.49', 'bet_below_min', '0.50000000'],
    'above max' => ['20.01', 'bet_above_max', '20.00000000'],
]);

it('rejects a currency the game has no limits for', function (): void {
    $game = Game::factory()->create();
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR']);

    limitedBet(Operator::factory()->create(), stake(limitedRound($game), '1.00', 'TRY'), 'k-try')
        ->assertStatus(422)
        ->assertJsonPath('error', 'currency_not_supported');

    expectNothingTaken();
});

it('rejects a game that is not enabled for the operator', function (): void {
    $game = Game::factory()->create(['access' => GameAccess::Listed]);

    limitedBet(Operator::factory()->create(), stake(limitedRound($game), '1.00'), 'k-listed')
        ->assertStatus(403)
        ->assertJsonPath('error', 'game_not_enabled');

    expectNothingTaken();
});

it('rejects a game that is not active', function (): void {
    $game = Game::factory()->create();
    $round = limitedRound($game);
    $game->forceFill(['status' => 'disabled'])->save();

    limitedBet(Operator::factory()->create(), stake($round, '1.00'), 'k-disabled')
        ->assertStatus(409)
        ->assertJsonPath('error', 'game_unavailable');

    expectNothingTaken();
});

it('rejects amounts bcmath cannot take with a 422 instead of a 500', function (string $amount): void {
    limitedBet(Operator::factory()->create(), stake(limitedRound(Game::factory()->create()), $amount), 'k-'.md5($amount))
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');

    expectNothingTaken();
})->with(['1e3', '+1', '0x1A', '12345678901']); // (padding is trimmed by TrimStrings and accepted, as before)

it('places a bet within the limits with the game and the max payout fixed on it', function (): void {
    $game = Game::factory()->create(['access' => GameAccess::Listed]);
    $operator = Operator::factory()->create();
    $operatorGame = OperatorGame::factory()->create(['operator_id' => $operator->id, 'game_id' => $game->id]);
    GameLimit::factory()->create(['game_id' => $game->id, 'currency' => 'EUR', 'min_bet' => '0.5', 'max_bet' => '20', 'max_win' => '1000']);
    $operatorGame->limits()->create(['currency' => 'EUR', 'max_win' => '400']);

    limitedBet($operator, stake(limitedRound($game), '20.00'), 'k-ok')->assertCreated();

    $bet = Bet::sole();
    expect($bet->game_id)->toBe($game->id)
        ->and((string) $bet->max_payout)->toBe('400.00000000');
});

it('keeps the legacy behaviour for a game without limit rows: any currency, uncapped', function (): void {
    limitedBet(Operator::factory()->create(), stake(limitedRound(Game::factory()->create()), '5000.00', 'TRY'), 'k-legacy')
        ->assertCreated();

    expect(Bet::sole()->max_payout)->toBeNull();
});
