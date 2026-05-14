<?php

declare(strict_types=1);

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Support\Facades\Cache;

function signedFor(Operator $operator, array $body, string $idempotencyKey, int $timestamp): array
{
    $bodyJson = json_encode($body);

    $signature = OperatorSignature::compute(
        secret: $operator->secret_key,
        method: 'POST',
        path: '/v1/ping',
        body: $bodyJson,
        timestamp: $timestamp,
    );

    return [
        'X-Operator-Id' => $operator->id,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => $signature,
        'Idempotency-Key' => $idempotencyKey,
    ];
}

beforeEach(function (): void {
    Cache::flush();
});

it('returns the cached response when the same idempotency key replays the same payload', function (): void {
    $operator = Operator::factory()->create();
    $body = ['amount' => 1.5];
    $ts = time();

    $first = $this->withHeaders(signedFor($operator, $body, 'idem-A', $ts))
        ->postJson('/v1/ping', $body);

    $first->assertCreated();
    $firstReceivedAt = $first->json('received_at');

    $second = $this->withHeaders(signedFor($operator, $body, 'idem-A', $ts + 1))
        ->postJson('/v1/ping', $body);

    $second->assertCreated()
        ->assertHeader('Idempotent-Replay', 'true')
        ->assertJsonPath('received_at', $firstReceivedAt);
});

it('returns 409 when the same idempotency key is reused with a different payload', function (): void {
    $operator = Operator::factory()->create();
    $bodyA = ['amount' => 1.0];
    $bodyB = ['amount' => 999.0];
    $ts = time();

    $this->withHeaders(signedFor($operator, $bodyA, 'idem-X', $ts))
        ->postJson('/v1/ping', $bodyA)
        ->assertCreated();

    $conflict = $this->withHeaders(signedFor($operator, $bodyB, 'idem-X', $ts + 1))
        ->postJson('/v1/ping', $bodyB);

    $conflict->assertStatus(409)
        ->assertJsonPath('error', 'idempotency_conflict');
});

it('isolates idempotency caches per operator', function (): void {
    $operatorA = Operator::factory()->create();
    $operatorB = Operator::factory()->create();
    $body = ['x' => 1];
    $ts = time();

    $this->withHeaders(signedFor($operatorA, $body, 'shared-key', $ts))
        ->postJson('/v1/ping', $body)
        ->assertCreated();

    $second = $this->withHeaders(signedFor($operatorB, $body, 'shared-key', $ts + 1))
        ->postJson('/v1/ping', $body);

    $second->assertCreated()
        ->assertHeaderMissing('Idempotent-Replay');
});

it('rejects mutation requests that are missing an idempotency key', function (): void {
    $operator = Operator::factory()->create();
    $body = ['x' => 1];
    $headers = signedFor($operator, $body, 'unused', time());
    unset($headers['Idempotency-Key']);

    $response = $this->withHeaders($headers)->postJson('/v1/ping', $body);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'missing_idempotency_key');
});
