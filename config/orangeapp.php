<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OrangeApp Operator (iOS token app)
|--------------------------------------------------------------------------
|
| Consumed by OrangeAppOperatorSeeder. OrangeApp plays with the virtual,
| non-monetary currency TKN. Players are auto-created on first bet.
|
*/

return [
    'operator_slug' => env('ORANGE_APP_SLUG', 'orangeapp'),
    'operator_name' => env('ORANGE_APP_NAME', 'OrangeApp (iOS, tokens)'),
    'secret_key' => env('ORANGE_APP_SECRET'),
    'callback_base_url' => env('ORANGE_APP_CALLBACK_URL', 'http://localhost:8002/api'),
    'ip_whitelist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ORANGE_APP_IPS', '127.0.0.1,::1')),
    ))),
    'default_currency' => 'TKN',
];
