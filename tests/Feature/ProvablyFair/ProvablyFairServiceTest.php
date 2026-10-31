<?php

declare(strict_types=1);

use App\Domain\Game\Models\Game;
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

it('derives divisor from a game\'s house_edge', function (): void {
    $svc = new ProvablyFairService;

    $thirty3 = Game::factory()->make(['rtp_target' => 0.97, 'house_edge' => 0.03]);
    $hundred = Game::factory()->make(['rtp_target' => 0.99, 'house_edge' => 0.01]);
    $ten = Game::factory()->make(['rtp_target' => 0.90, 'house_edge' => 0.10]);

    expect($svc->divisorForGame($thirty3))->toBe(33);
    expect($svc->divisorForGame($hundred))->toBe(100);
    expect($svc->divisorForGame($ten))->toBe(10);
});

it('rejects an out-of-range house_edge when deriving divisor', function (): void {
    $svc = new ProvablyFairService;
    $bad = Game::factory()->make(['rtp_target' => 1.0, 'house_edge' => 0.0]);

    $svc->divisorForGame($bad);
})->throws(InvalidArgumentException::class);

it('a smaller house_edge collapses fewer rounds to 1.00x', function (): void {
    $svc = new ProvablyFairService;

    // 5k rounds, same seeds, only the divisor changes.
    $hits = function (int $divisor) use ($svc): int {
        $count = 0;
        foreach (range(1, 5000) as $nonce) {
            if ($svc->crashPoint('edge-compare', 'house', $nonce, $divisor) === 1.00) {
                $count++;
            }
        }

        return $count;
    };

    $hits33 = $hits(33);   // expected ≈ 5000/33 ≈ 151
    $hits100 = $hits(100);  // expected ≈ 5000/100 ≈ 50

    // 100-divisor must produce strictly fewer instant-crashes than 33-divisor.
    expect($hits100)->toBeLessThan($hits33);
});
