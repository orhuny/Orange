<?php

declare(strict_types=1);

namespace App\Domain\Game\Services;

use App\Domain\Game\Models\GameRound;
use Illuminate\Support\Carbon;

final class MultiplierCalculator
{
    public const MIN_MULTIPLIER = 1.00;

    public function __construct(
        private readonly float $growthRate,
    ) {}

    public static function default(): self
    {
        return new self(growthRate: (float) config('crash.growth_rate', 0.06));
    }

    public function atElapsed(float $elapsedSeconds): float
    {
        if ($elapsedSeconds <= 0.0) {
            return self::MIN_MULTIPLIER;
        }

        return round(exp($this->growthRate * $elapsedSeconds), 2);
    }

    public function forRound(GameRound $round, ?Carbon $now = null): float
    {
        if ($round->started_at === null) {
            return self::MIN_MULTIPLIER;
        }

        $now ??= Carbon::now();
        $endpoint = $round->crashed_at ?? $now;

        $elapsedSeconds = abs($round->started_at->diffInMicroseconds($endpoint)) / 1_000_000;
        $live = $this->atElapsed($elapsedSeconds);

        if ($round->crash_point !== null) {
            return min($live, (float) $round->crash_point);
        }

        return $live;
    }

    public function durationToReach(float $targetMultiplier): float
    {
        if ($targetMultiplier <= self::MIN_MULTIPLIER) {
            return 0.0;
        }

        return log($targetMultiplier) / $this->growthRate;
    }
}
