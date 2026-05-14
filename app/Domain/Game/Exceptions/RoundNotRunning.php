<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Enums\RoundState;
use RuntimeException;

class RoundNotRunning extends RuntimeException
{
    public static function for(RoundState $state): self
    {
        return new self("Cashout is only valid while the round is running; current state is {$state->value}.");
    }
}
