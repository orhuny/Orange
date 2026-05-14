<?php

declare(strict_types=1);

use App\Domain\ProvablyFair\Services\ProvablyFairService;

it('verify endpoint returns crash point and hash for valid input', function (): void {
    $svc = new ProvablyFairService;
    $serverSeed = 'server-seed-test-1234';
    $clientSeed = 'client-seed-x';
    $nonce = 7;

    $response = $this->postJson('/v1/fairness/verify', [
        'server_seed' => $serverSeed,
        'client_seed' => $clientSeed,
        'nonce' => $nonce,
    ]);

    $response->assertOk()
        ->assertJsonPath('crash_point', $svc->crashPoint($serverSeed, $clientSeed, $nonce))
        ->assertJsonPath('server_seed_hash', hash('sha256', $serverSeed))
        ->assertJsonPath('hmac', $svc->hmac($serverSeed, $clientSeed, $nonce))
        ->assertJsonPath('expected_hash_matches', null);
});

it('flags hash mismatch when expected_server_seed_hash is provided', function (): void {
    $response = $this->postJson('/v1/fairness/verify', [
        'server_seed' => 'real-seed',
        'client_seed' => 'client',
        'nonce' => 1,
        'expected_server_seed_hash' => str_repeat('0', 64),
    ]);

    $response->assertOk()->assertJsonPath('expected_hash_matches', false);
});

it('confirms hash match when correct hash is provided', function (): void {
    $serverSeed = 'real-seed';
    $response = $this->postJson('/v1/fairness/verify', [
        'server_seed' => $serverSeed,
        'client_seed' => 'client',
        'nonce' => 1,
        'expected_server_seed_hash' => hash('sha256', $serverSeed),
    ]);

    $response->assertOk()->assertJsonPath('expected_hash_matches', true);
});

it('rejects requests missing required fields', function (): void {
    $this->postJson('/v1/fairness/verify', [])
        ->assertStatus(422);
});

it('verify endpoint is public (no HMAC headers required)', function (): void {
    $this->postJson('/v1/fairness/verify', [
        'server_seed' => 's',
    ])->assertStatus(422); // 422, not 401 — proves it bypassed VerifyOperatorSignature
});
