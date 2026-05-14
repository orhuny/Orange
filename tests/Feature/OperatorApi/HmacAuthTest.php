<?php

declare(strict_types=1);

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Support\Facades\Cache;

function signedHeaders(Operator $operator, string $method, string $path, array|string $body, ?int $timestamp = null, string $idempotencyKey = 'pest-key-1'): array
{
    $timestamp ??= time();
    $bodyJson = is_string($body) ? $body : json_encode($body);

    $signature = OperatorSignature::compute(
        secret: $operator->secret_key,
        method: $method,
        path: $path,
        body: $bodyJson,
        timestamp: $timestamp,
    );

    return [
        'X-Operator-Id' => $operator->id,
        'X-Timestamp' => (string) $timestamp,
        'X-Signature' => $signature,
        'Idempotency-Key' => $idempotencyKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}

beforeEach(function (): void {
    Cache::flush();
});

it('accepts a correctly signed request', function (): void {
    $operator = Operator::factory()->create();
    $body = ['hello' => 'world'];

    $response = $this->withHeaders(signedHeaders($operator, 'POST', '/v1/ping', $body))
        ->postJson('/v1/ping', $body);

    $response->assertCreated()
        ->assertJsonPath('operator_id', $operator->id)
        ->assertJsonPath('echo.hello', 'world');
});

it('rejects when signature headers are missing', function (): void {
    $response = $this->postJson('/v1/ping', ['x' => 1]);

    $response->assertUnauthorized()
        ->assertJsonPath('reason', 'missing_signature_headers');
});

it('rejects when timestamp is outside the allowed window', function (): void {
    $operator = Operator::factory()->create();
    $body = ['x' => 1];

    $response = $this->withHeaders(signedHeaders($operator, 'POST', '/v1/ping', $body, time() - 600))
        ->postJson('/v1/ping', $body);

    $response->assertUnauthorized()
        ->assertJsonPath('reason', 'timestamp_outside_window');
});

it('rejects an unknown operator', function (): void {
    $body = ['x' => 1];
    $headers = [
        'X-Operator-Id' => '00000000-0000-0000-0000-000000000000',
        'X-Timestamp' => (string) time(),
        'X-Signature' => str_repeat('0', 64),
        'Idempotency-Key' => 'k',
    ];

    $response = $this->withHeaders($headers)->postJson('/v1/ping', $body);

    $response->assertUnauthorized()
        ->assertJsonPath('reason', 'unknown_operator');
});

it('rejects a suspended operator', function (): void {
    $operator = Operator::factory()->suspended()->create();
    $body = ['x' => 1];

    $response = $this->withHeaders(signedHeaders($operator, 'POST', '/v1/ping', $body))
        ->postJson('/v1/ping', $body);

    $response->assertUnauthorized()
        ->assertJsonPath('reason', 'operator_inactive');
});

it('rejects a request whose body has been tampered with', function (): void {
    $operator = Operator::factory()->create();
    $headers = signedHeaders($operator, 'POST', '/v1/ping', ['original' => true]);

    $response = $this->withHeaders($headers)->postJson('/v1/ping', ['tampered' => true]);

    $response->assertUnauthorized()
        ->assertJsonPath('reason', 'signature_mismatch');
});

it('rejects a replayed request with the same timestamp+signature', function (): void {
    $operator = Operator::factory()->create();
    $body = ['n' => 1];
    $headers = signedHeaders($operator, 'POST', '/v1/ping', $body);

    $this->withHeaders($headers)->postJson('/v1/ping', $body)->assertCreated();

    $headers['Idempotency-Key'] = 'pest-key-2';
    $replay = $this->withHeaders($headers)->postJson('/v1/ping', $body);

    $replay->assertUnauthorized()
        ->assertJsonPath('reason', 'replay_detected');
});

it('the operator status enum gates api access', function (): void {
    expect(OperatorStatus::Active->canCallApi())->toBeTrue()
        ->and(OperatorStatus::Suspended->canCallApi())->toBeFalse()
        ->and(OperatorStatus::Disabled->canCallApi())->toBeFalse();
});
