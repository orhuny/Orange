<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use Carbon\CarbonImmutable;

/**
 * A player's balance as their operator's wallet last reported it. The operator's wallet is
 * the system of record: Orange never works a balance out itself, it only passes on the
 * balance_after of the newest wallet call the operator answered.
 */
final readonly class ReportedBalance
{
    public function __construct(
        /** As the wallet spelt it ("98.50"); null: no wallet call has reported one yet. */
        public ?string $balance,
        /** The wallet transaction whose answer carried it. */
        public ?int $txId,
        /** When Orange got that answer. */
        public ?CarbonImmutable $reportedAt,
        /**
         * A wallet call since the given time is still unanswered: a win or a refund is on its
         * way to the wallet (or a stake is being taken), so the balance is about to change.
         */
        public bool $pending,
    ) {}
}
