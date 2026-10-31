<?php

declare(strict_types=1);

namespace App\Domain\Game\Limits;

/**
 * The limits a bet is played with: the game's limits for the currency, tightened by
 * the operator's. Decimal strings (8 dp); null means no limit.
 */
final readonly class EffectiveLimits
{
    public function __construct(
        public string $currency,
        public ?string $minBet,
        public ?string $maxBet,
        public ?string $maxWin,
    ) {}

    public static function unlimited(string $currency): self
    {
        return new self($currency, null, null, null);
    }

    /** The payout, capped at max win. */
    public function capPayout(string $payout): string
    {
        return $this->maxWin !== null && bccomp($payout, $this->maxWin, 8) > 0 ? $this->maxWin : $payout;
    }
}
