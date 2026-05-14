<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use RuntimeException;

class AlreadyCashedOut extends RuntimeException
{
    public static function for(int $betId): self
    {
        return new self("Bet {$betId} has already been settled.");
    }
}
