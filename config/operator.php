<?php

declare(strict_types=1);

return [
    'timestamp_window_seconds' => (int) env('OPERATOR_API_TIMESTAMP_WINDOW_SECONDS', 30),
    'idempotency_ttl_hours' => (int) env('OPERATOR_API_IDEMPOTENCY_TTL_HOURS', 24),
    'default_rate_limit_per_minute' => (int) env('OPERATOR_API_DEFAULT_RATE_LIMIT_PER_MINUTE', 600),
    'replay_cache_ttl_seconds' => 60,
];
