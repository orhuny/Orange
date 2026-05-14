<?php

declare(strict_types=1);

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Services\RoundOrchestrator;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;

function placeBetHeaders(Operator $operator, array $payload, string $idempotencyKey, ?int $timestamp = null): array
{
    $timestamp ??= time();
    $signature = OperatorSignature::compute(
        secret: $operator->secret_key,
        method: 'POST',
        path: '/v1/bets',
        body: json_encode($payload),
        timestamp: $timestamp,
    );

    return [
        'X-Operator-Id' => $operator->id,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => $signature,
        'Idempotency-Key' => $idempotencyKey,
    ];
}

function freshBettingRound(): array
{
    Cache::flush();
    $operator = Operator::factory()->create();
    $game = Game::factory()->create();
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->openBetting($orchestrator->create($game));

    return [$operator, $round];
}

it('places a bet during the betting window and returns 201', function (): void {
    [$operator, $round] = freshBettingRound();

    $payload = [
        'player_external_id' => 'player-1',
        'round_uuid' => $round->round_uuid,
        'amount' => '2.50',
        'currency' => 'EUR',
    ];

    $response = $this->withHeaders(placeBetHeaders($operator, $payload, 'bet-1'))
        ->postJson('/v1/bets', $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'placed')
        ->assertJsonPath('round_uuid', $round->round_uuid)
        ->assertJsonPath('amount', '2.50000000')
        ->assertJsonPath('currency', 'EUR');

    expect(Bet::count())->toBe(1)
        ->and(WalletTransaction::count())->toBe(1)
        ->and(WalletTransaction::first()->type->value)->toBe('debit');

    $round->refresh();
    expect((string) $round->total_bet_amount)->toBe('2.50000000');
});

it('rejects a bet placed against a round in Running state', function (): void {
    [$operator, $round] = freshBettingRound();
    $orchestrator = app(RoundOrchestrator::class);
    $round = $orchestrator->start($round);
    expect($round->state)->toBe(RoundState::Running);

    $payload = [
        'player_external_id' => 'player-1',
        'round_uuid' => $round->round_uuid,
        'amount' => '1.00',
        'currency' => 'EUR',
    ];

    $response = $this->withHeaders(placeBetHeaders($operator, $payload, 'late-bet'))
        ->postJson('/v1/bets', $payload);

    $response->assertStatus(409)
        ->assertJsonPath('error', 'betting_closed');

    expect(Bet::count())->toBe(0);
});

it('returns 404 when round_uuid is unknown', function (): void {
    [$operator] = freshBettingRound();

    $payload = [
        'player_external_id' => 'p',
        'round_uuid' => '00000000-0000-0000-0000-000000000000',
        'amount' => '1.00',
        'currency' => 'EUR',
    ];

    $this->withHeaders(placeBetHeaders($operator, $payload, 'no-round'))
        ->postJson('/v1/bets', $payload)
        ->assertStatus(404);
});

it('idempotent replay of the same bet returns the cached response and persists no extra rows', function (): void {
    [$operator, $round] = freshBettingRound();

    $payload = [
        'player_external_id' => 'player-replay',
        'round_uuid' => $round->round_uuid,
        'amount' => '5.00',
        'currency' => 'EUR',
    ];

    $first = $this->withHeaders(placeBetHeaders($operator, $payload, 'idempo-bet'))
        ->postJson('/v1/bets', $payload);
    $first->assertCreated();

    $second = $this->withHeaders(placeBetHeaders($operator, $payload, 'idempo-bet', time() + 1))
        ->postJson('/v1/bets', $payload);
    $second->assertCreated()
        ->assertHeader('Idempotent-Replay', 'true')
        ->assertJsonPath('bet_id', $first->json('bet_id'));

    expect(Bet::count())->toBe(1)
        ->and(WalletTransaction::count())->toBe(1);
});

it('rejects invalid payloads with 422', function (): void {
    [$operator, $round] = freshBettingRound();

    $payload = [
        'player_external_id' => 'p',
        'round_uuid' => $round->round_uuid,
        'amount' => '0',
        'currency' => 'EU',
    ];

    $this->withHeaders(placeBetHeaders($operator, $payload, 'bad-payload'))
        ->postJson('/v1/bets', $payload)
        ->assertStatus(422);
});
