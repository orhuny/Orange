<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;
use Throwable;

class WalletCallbackFailed extends RuntimeException
{
    /** @param  array<string, mixed>|null  $lastResponse */
    public function __construct(
        string $message,
        public readonly int $attempts,
        public readonly ?array $lastResponse = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
