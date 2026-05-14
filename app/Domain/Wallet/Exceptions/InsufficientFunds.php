<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

class InsufficientFunds extends RuntimeException
{
    public static function forPlayer(string $playerExternalId, string $amount, string $currency): self
    {
        return new self("Insufficient funds for player [{$playerExternalId}] for {$amount} {$currency}.");
    }
}
