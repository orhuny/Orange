<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Limits\EffectiveLimits;
use RuntimeException;

class StakeOutsideLimits extends RuntimeException
{
    /** @param  'bet_below_min'|'bet_above_max'  $reason  the API error code */
    public function __construct(string $message, public readonly string $reason, public readonly string $limit)
    {
        parent::__construct($message);
    }

    public static function belowMin(EffectiveLimits $limits): self
    {
        return new self("The minimum bet is {$limits->minBet} {$limits->currency}.", 'bet_below_min', (string) $limits->minBet);
    }

    public static function notBelowMaxWin(EffectiveLimits $limits): self
    {
        return new self("The stake must be below the maximum win of {$limits->maxWin} {$limits->currency}.", 'bet_above_max', (string) $limits->maxWin);
    }

    public static function aboveMax(EffectiveLimits $limits): self
    {
        return new self("The maximum bet is {$limits->maxBet} {$limits->currency}.", 'bet_above_max', (string) $limits->maxBet);
    }
}
