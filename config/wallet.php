<?php

declare(strict_types=1);

return [
    // Credits and rollbacks: sent from the queue, retried there and by wallet:reconcile.
    'callback' => [
        'timeout_seconds' => (int) env('WALLET_CALLBACK_TIMEOUT_SECONDS', 5),
        'retry_attempts' => (int) env('WALLET_CALLBACK_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => (int) env('WALLET_CALLBACK_RETRY_BACKOFF_MS', 500),
        // Every wallet call: an operator host that can't be reached fails fast.
        'connect_timeout_seconds' => (float) env('WALLET_CALLBACK_CONNECT_TIMEOUT_SECONDS', 2),
    ],

    // Direct play: the stake debit a player waits for. One more attempt (same tx_id, which
    // the operator applies once) when the first fails early (unreachable, a proxy error)
    // and enough time is left; never past the deadline. Then the bet is answered
    // 503 wallet_unavailable and the stake rolled back.
    'debit' => [
        'timeout_seconds' => (float) env('WALLET_DEBIT_TIMEOUT_SECONDS', 8),
        'deadline_seconds' => (float) env('WALLET_DEBIT_DEADLINE_SECONDS', 10),
        'min_retry_seconds' => (float) env('WALLET_DEBIT_MIN_RETRY_SECONDS', 3),
        'retry_backoff_ms' => (int) env('WALLET_DEBIT_RETRY_BACKOFF_MS', 100),
    ],

    // An instant game's win (dice, slots): credited while the player waits for the result,
    // so the result can carry the balance with the win paid in. Not answered within the
    // deadline, it goes on to the queue like any other credit (and to wallet:reconcile).
    'payout' => [
        'timeout_seconds' => (float) env('WALLET_PAYOUT_TIMEOUT_SECONDS', 4),
        'deadline_seconds' => (float) env('WALLET_PAYOUT_DEADLINE_SECONDS', 5),
        'min_retry_seconds' => (float) env('WALLET_PAYOUT_MIN_RETRY_SECONDS', 2),
        'retry_backoff_ms' => (int) env('WALLET_PAYOUT_RETRY_BACKOFF_MS', 100),
    ],

    // A stake debit that got no answer is rolled back, but only after this long: a slow
    // operator may still be applying that debit, and the refund must not overtake it.
    'unanswered_debit_rollback_delay_seconds' => 60,

    // wallet:reconcile (every minute) re-sends credits and rollbacks the operator has not
    // acknowledged: `failed` ones, and `pending` ones older than the grace period (the job
    // was lost). Debits are never re-sent; a failed debit is final and the bet flow owns it.
    'reconcile' => [
        // After this many reconcile attempts the row is left alone and a critical line is
        // logged on the `operator` channel for someone to settle it by hand.
        'max_attempts' => 12,
        // Wait after attempt N is backoff_seconds[N-1]; the last value repeats. With the
        // defaults the 12th and final attempt lands about 6.8 hours after the first.
        'backoff_seconds' => [60, 120, 300, 600, 1800, 3600],
        // A `pending` row younger than this may still have its original job in the queue.
        'pending_grace_seconds' => 300,
        'batch_size' => 100,
    ],

    'webhooks' => [
        'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
        'timeout_seconds' => (int) env('WEBHOOK_TIMEOUT_SECONDS', 5),
        'retry_backoff_seconds' => [10, 30, 120, 600, 1800],
    ],
];
