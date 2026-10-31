<?php

declare(strict_types=1);

return [
    'callback' => [
        'timeout_seconds' => (int) env('WALLET_CALLBACK_TIMEOUT_SECONDS', 5),
        'retry_attempts' => (int) env('WALLET_CALLBACK_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => (int) env('WALLET_CALLBACK_RETRY_BACKOFF_MS', 500),
    ],

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
