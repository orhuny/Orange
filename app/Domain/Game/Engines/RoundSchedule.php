<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines;

/** Timing of a round-based game's loop. */
final readonly class RoundSchedule
{
    public function __construct(
        public int $waitingSeconds,
        public int $bettingSeconds,
        public int $tickIntervalMs,
    ) {}
}
