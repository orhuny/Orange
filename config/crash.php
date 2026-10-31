<?php

declare(strict_types=1);

return [
    'growth_rate' => (float) env('GAME_CRASH_GROWTH_RATE', 0.06),
    'waiting_seconds' => (int) env('GAME_CRASH_WAITING_SECONDS', 5),
    'betting_seconds' => (int) env('GAME_CRASH_BETTING_SECONDS', 10),
    'tick_interval_ms' => (int) env('GAME_CRASH_TICK_INTERVAL_MS', 100),
    'house_edge_divisor' => (int) env('GAME_CRASH_HOUSE_EDGE_DIVISOR', 33),

    // Studio intro shown over /crash on launch.
    // - No images in public/images/intro/: a built-in scene animates the game's
    //   own art (submarine, background, creatures) with the title below.
    // - Images in public/images/intro/landscape/ (and optionally portrait/ for
    //   tall screens) replace it, cross-faded in file-name order: 01.webp, 02.webp, ...
    'intro' => [
        'enabled' => true,
        'title' => 'DeepDive',
        'frame_ms' => 550,          // frames mode: how long each frame stays on screen
        'fade_ms' => 400,           // frames mode: cross-fade; both modes: final fade into the game
        'hold_ms' => [6 => 1300],   // per-frame overrides (1-based), e.g. the title frame
        'once_per_session' => false, // true = only the first launch per browser tab session
    ],
];
