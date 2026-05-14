<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Enums\RoundState;
use RuntimeException;

class BettingClosed extends RuntimeException
{
    public static function for(RoundState $state): self
    {
        return new self("Round is in state [{$state->value}] and is not accepting bets.");
    }
}
