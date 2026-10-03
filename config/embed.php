<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Embed (iframe) allowed origins
    |--------------------------------------------------------------------------
    |
    | Origins permitted to embed the crash game via iframe.
    | Comma-separated values from EMBED_ALLOWED_ORIGINS.
    |
    | Example:
    |   EMBED_ALLOWED_ORIGINS=http://localhost:8001,https://operator.example.com
    |
    */

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', (string) env('EMBED_ALLOWED_ORIGINS', 'http://localhost:8001,http://127.0.0.1:8001'))),
    ),
];
