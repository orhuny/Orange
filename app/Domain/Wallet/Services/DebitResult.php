<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Models\WalletTransaction;

/**
 * The operator's answer to a synchronous stake debit.
 *
 * - accepted: the stake was taken.
 * - rejected: the operator definitively refused (4xx); nothing was taken.
 * - unknown:  no definite answer (timeout, 5xx); the stake may or may not have been
 *             taken, so the caller must roll it back.
 */
final readonly class DebitResult
{
    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const UNKNOWN = 'unknown';

    public function __construct(
        public WalletTransaction $transaction,
        public string $outcome,
        public ?string $error = null,
        public ?string $balance = null,
    ) {}

    public function accepted(): bool
    {
        return $this->outcome === self::ACCEPTED;
    }
}
