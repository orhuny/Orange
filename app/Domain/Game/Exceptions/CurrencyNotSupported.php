<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Models\Game;
use RuntimeException;

class CurrencyNotSupported extends RuntimeException
{
    public static function for(Game $game, string $currency): self
    {
        return new self("Game [{$game->code}] has no limits for currency [{$currency}].");
    }
}
