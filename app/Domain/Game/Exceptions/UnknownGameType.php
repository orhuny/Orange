<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use RuntimeException;

class UnknownGameType extends RuntimeException
{
    public static function named(string $type): self
    {
        return new self("No game engine is registered for game type [{$type}].");
    }
}
