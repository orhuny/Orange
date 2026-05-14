<?php

declare(strict_types=1);

use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use Illuminate\Support\Carbon;

it('returns 1.00x at t=0', function (): void {
    $calc = new MultiplierCalculator(0.06);
    expect($calc->atElapsed(0.0))->toBe(1.0);
});

it('grows exponentially at the configured rate', function (): void {
    $calc = new MultiplierCalculator(0.06);

    expect($calc->atElapsed(10.0))->toBe(round(exp(0.6), 2))
        ->and($calc->atElapsed(20.0))->toBe(round(exp(1.2), 2))
        ->and($calc->atElapsed(30.0))->toBe(round(exp(1.8), 2));
});

it('is monotonically increasing', function (): void {
    $calc = new MultiplierCalculator(0.06);
    $previous = 0.0;

    foreach (range(0, 50) as $t) {
        $current = $calc->atElapsed((float) $t);
        expect($current)->toBeGreaterThanOrEqual($previous);
        $previous = $current;
    }
});

it('caps at the persisted crash_point once a round is set', function (): void {
    Carbon::setTestNow('2026-05-14 12:00:00');

    $round = new GameRound([
        'started_at' => Carbon::now()->subSeconds(60),
        'crash_point' => 1.50,
    ]);
    $round->setRawAttributes($round->getAttributes() + ['id' => 1]);

    $calc = new MultiplierCalculator(0.06);

    expect($calc->forRound($round, Carbon::now()))->toBe(1.5);

    Carbon::setTestNow();
});

it('durationToReach inverts the curve', function (): void {
    $calc = new MultiplierCalculator(0.06);
    $duration = $calc->durationToReach(2.0);

    expect($duration)->toEqualWithDelta(log(2.0) / 0.06, 0.0001);
    expect($calc->atElapsed($duration))->toBe(2.0);
});
