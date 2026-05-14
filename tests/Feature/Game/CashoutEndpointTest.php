<?php

declare(strict_types=1);

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

function signedCashoutHeaders(Operator $operator, int $betId, string $idempotencyKey, ?int $timestamp = null): array
{
    $timestamp ??= time();
    $body = '[]';
    $path = "/v1/bets/{$betId}/cashout";

    $signature = OperatorSignature::compute(
        secret: $operator->secret_key,
        method: 'POST',
        path: $path,
        body: $body,
        timestamp: $timestamp,
    );

    return [
        'X-Operator-Id' => $operator->id,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => $signature,
        'Idempotency-Key' => $idempotencyKey,
    ];
}

function placedBetForOperator(Operator $operator): Bet
{
    $game = Game::factory()->create();
    $orch = app(RoundOrchestrator::class);

    $round = $orch->create($game);
    $round = $orch->openBetting($round);
    $round = $orch->start($round);
    $round->forceFill(['crash_point' => 100.00, 'started_at' => Carbon::now()->subSeconds(2)])->save();

    $player = Player::factory()->create(['operator_id' => $operator->id]);

    return Bet::factory()->create([
        'round_id' => $round->id,
        'operator_id' => $operator->id,
        'player_id' => $player->id,
        'amount' => '5.00000000',
        'currency' => 'EUR',
        'status' => BetStatus::Placed,
        'placed_at' => now(),
        'idempotency_key' => 'placed-'.uniqid(),
    ]);
}

beforeEach(fn () => Cache::flush());

it('cashout endpoint returns 401 without HMAC headers', function (): void {
    $this->postJson('/v1/bets/1/cashout')->assertUnauthorized();
});

it('cashout endpoint settles a placed bet and returns multiplier+payout', function (): void {
    $operator = Operator::factory()->create();
    $bet = placedBetForOperator($operator);

    $response = $this->withHeaders(signedCashoutHeaders($operator, (int) $bet->id, 'cash-1'))
        ->postJson("/v1/bets/{$bet->id}/cashout");

    $response->assertOk()
        ->assertJsonPath('bet_id', $bet->id)
        ->assertJsonStructure(['cashout_id', 'bet_id', 'multiplier', 'payout_amount', 'cashed_out_at', 'is_auto']);

    expect($bet->fresh()->status)->toBe(BetStatus::Won);
});

it('cashout endpoint returns 404 when the bet does not belong to the operator', function (): void {
    $owner = Operator::factory()->create();
    $other = Operator::factory()->create();
    $bet = placedBetForOperator($owner);

    $response = $this->withHeaders(signedCashoutHeaders($other, (int) $bet->id, 'cash-2'))
        ->postJson("/v1/bets/{$bet->id}/cashout");

    $response->assertNotFound()
        ->assertJsonPath('error', 'bet_not_found');
});

it('cashout endpoint returns 409 when the bet has already been settled', function (): void {
    $operator = Operator::factory()->create();
    $bet = placedBetForOperator($operator);
    $ts = time();

    $this->withHeaders(signedCashoutHeaders($operator, (int) $bet->id, 'cash-3', $ts))
        ->postJson("/v1/bets/{$bet->id}/cashout")
        ->assertOk();

    $response = $this->withHeaders(signedCashoutHeaders($operator, (int) $bet->id, 'cash-4', $ts + 1))
        ->postJson("/v1/bets/{$bet->id}/cashout");

    $response->assertStatus(409)
        ->assertJsonPath('error', 'already_settled');
});
