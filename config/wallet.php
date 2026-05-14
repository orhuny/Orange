<?php

declare(strict_types=1);

return [
    'callback' => [
        'timeout_seconds' => (int) env('WALLET_CALLBACK_TIMEOUT_SECONDS', 5),
        'retry_attempts' => (int) env('WALLET_CALLBACK_RETRY_ATTEMPTS', 3),
        'retry_backoff_ms' => (int) env('WALLET_CALLBACK_RETRY_BACKOFF_MS', 500),
    ],

    'webhooks' => [
        'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 5),
        'timeout_seconds' => (int) env('WEBHOOK_TIMEOUT_SECONDS', 5),
        'retry_backoff_seconds' => [10, 30, 120, 600, 1800],
    ],
];
