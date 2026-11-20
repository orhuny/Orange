<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

/**
 * How long one wallet call to an operator may take and how often it is tried. A retry
 * re-sends the same tx_id, which the operator applies at most once (docs/operator-integration.md §8).
 */
final readonly class WalletCallBudget
{
    /**
     * @param  float|null  $deadlineSeconds  all attempts together; null: no overall limit
     * @param  float  $minRetrySeconds  no further attempt with less time than this left before the deadline
     */
    public function __construct(
        public int $maxAttempts,
        public float $timeoutSeconds,
        public float $connectTimeoutSeconds,
        public int $backoffMs,
        public ?float $deadlineSeconds = null,
        public float $minRetrySeconds = 0.0,
    ) {}

    /** Credits and rollbacks, sent from the queue: nobody waits on them. */
    public static function callback(): self
    {
        return new self(
            maxAttempts: max(1, (int) config('wallet.callback.retry_attempts', 3)),
            timeoutSeconds: (float) config('wallet.callback.timeout_seconds', 5),
            connectTimeoutSeconds: (float) config('wallet.callback.connect_timeout_seconds', 2),
            backoffMs: (int) config('wallet.callback.retry_backoff_ms', 500),
        );
    }

    /**
     * An instant game's win (WalletService::creditNow): the player waits for the result, so it
     * has a deadline. Unanswered by then, it goes on to the queue with callback()'s budget.
     */
    public static function payout(): self
    {
        return new self(
            maxAttempts: 2,
            timeoutSeconds: (float) config('wallet.payout.timeout_seconds', 4),
            connectTimeoutSeconds: (float) config('wallet.callback.connect_timeout_seconds', 2),
            backoffMs: (int) config('wallet.payout.retry_backoff_ms', 100),
            deadlineSeconds: (float) config('wallet.payout.deadline_seconds', 5),
            minRetrySeconds: (float) config('wallet.payout.min_retry_seconds', 2),
        );
    }

    /** Direct play's stake debit: a player waits on it, so it has a deadline. */
    public static function debit(): self
    {
        return new self(
            maxAttempts: 2,
            timeoutSeconds: (float) config('wallet.debit.timeout_seconds', 8),
            connectTimeoutSeconds: (float) config('wallet.callback.connect_timeout_seconds', 2),
            backoffMs: (int) config('wallet.debit.retry_backoff_ms', 100),
            deadlineSeconds: (float) config('wallet.debit.deadline_seconds', 10),
            minRetrySeconds: (float) config('wallet.debit.min_retry_seconds', 3),
        );
    }
}
