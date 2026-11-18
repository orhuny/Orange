<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

/** One bet's result. Amounts are hundredths of the base bet. */
final readonly class SpinResult
{
    /** @param  array<string, mixed>|null  $outcome  the public outcome (null when not recorded, e.g. in the simulator) */
    public function __construct(
        public int $win,
        public int $baseWin,
        public int $freeSpinsWin,
        public bool $triggered,
        public int $freeSpins,
        public bool $maxWinReached,
        public ?array $outcome,
    ) {}
}
