<?php

declare(strict_types=1);

return [
    'growth_rate' => (float) env('GAME_CRASH_GROWTH_RATE', 0.06),
    'waiting_seconds' => (int) env('GAME_CRASH_WAITING_SECONDS', 5),
    'betting_seconds' => (int) env('GAME_CRASH_BETTING_SECONDS', 10),
    'tick_interval_ms' => (int) env('GAME_CRASH_TICK_INTERVAL_MS', 100),
    'house_edge_divisor' => (int) env('GAME_CRASH_HOUSE_EDGE_DIVISOR', 33),
];
