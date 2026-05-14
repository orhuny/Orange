<?php

declare(strict_types=1);

use App\Domain\ProvablyFair\Services\ProvablyFairService;

it('hmac is deterministic for the same inputs', function (): void {
    $svc = new ProvablyFairService;

    expect($svc->hmac('server', 'client', 1))
        ->toBe($svc->hmac('server', 'client', 1))
        ->and($svc->hmac('server', 'client', 1))
        ->not->toBe($svc->hmac('server', 'client', 2));
});

it('crash point is deterministic for the same inputs', function (): void {
    $svc = new ProvablyFairService;

    $a = $svc->crashPoint('server-seed-aaaa', 'client-seed', 42);
    $b = $svc->crashPoint('server-seed-aaaa', 'client-seed', 42);

    expect($a)->toBe($b)->and($a)->toBeGreaterThanOrEqual(1.00);
});

it('returns 1.00 when the short hash hits the house-edge boundary', function (): void {
    $svc = new ProvablyFairService;

    // hex 0..ffffffff where the int value % 33 == 0
    $hmac = '00000021'.str_repeat('0', 56); // 0x21 = 33
    expect($svc->crashFromHmac($hmac))->toBe(1.00);
});

it('crash is at least the minimum multiplier', function (): void {
    $svc = new ProvablyFairService;

    foreach (range(1, 200) as $nonce) {
        expect($svc->crashPoint('regression-seed', 'house', $nonce))
            ->toBeGreaterThanOrEqual(ProvablyFairService::MIN_MULTIPLIER);
    }
});

it('approaches expected RTP under sample distribution', function (): void {
    $svc = new ProvablyFairService;
    $rounds = 5000;
    $totalBet = $rounds;
    $totalPayout = 0.0;

    foreach (range(1, $rounds) as $nonce) {
        $crash = $svc->crashPoint('rtp-seed', 'rtp-client', $nonce);
        // Player auto-cashes-out at 1.50x; if crash >= 1.50 they win 1.50, else lose 1
        if ($crash >= 1.50) {
            $totalPayout += 1.50;
        }
    }

    $rtp = $totalPayout / $totalBet;
    // House edge ~3% so RTP should be close to 0.97; very wide band for 5k rounds
    expect($rtp)->toBeGreaterThan(0.85)->toBeLessThan(1.05);
});

it('hashSeed returns sha256 hex', function (): void {
    $svc = new ProvablyFairService;

    expect($svc->hashSeed('abc'))
        ->toBe(hash('sha256', 'abc'))
        ->toHaveLength(64);
});
