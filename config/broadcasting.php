<?php

declare(strict_types=1);

return [
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                // Seconds a broadcast may take (Pusher's default is 30). crash:loop and
                // crash:tick broadcast inline: a stuck Reverb must not stall a round.
                'timeout' => (int) env('REVERB_TIMEOUT', 2),
            ],
            'client_options' => [
                // Guzzle options
                'connect_timeout' => (float) env('REVERB_CONNECT_TIMEOUT', 1),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
