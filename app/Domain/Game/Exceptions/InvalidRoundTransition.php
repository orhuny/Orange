<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Enums\RoundState;
use RuntimeException;

class InvalidRoundTransition extends RuntimeException
{
    public static function from(RoundState $current, RoundState $target): self
    {
        return new self("Cannot transition round from [{$current->value}] to [{$target->value}].");
    }
}
