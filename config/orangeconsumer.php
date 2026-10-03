<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OrangeConsumer Reference Operator
|--------------------------------------------------------------------------
|
| Configuration consumed by ConsumerOperatorSeeder to provision the
| OrangeConsumer-side test operator (and by any future code that needs
| to look it up by slug). See docs/orangeconsumer-integration.md.
|
*/

return [
    'operator_slug' => env('ORANGE_CONSUMER_SLUG', 'orange-consumer'),
    'operator_name' => env('ORANGE_CONSUMER_NAME', 'OrangeConsumer Reference Operator'),
    'secret_key' => env('ORANGE_CONSUMER_SECRET'),
    'callback_base_url' => env('ORANGE_CONSUMER_CALLBACK_URL', 'http://localhost:8001/api'),
    'ip_whitelist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ORANGE_CONSUMER_IPS', '127.0.0.1,::1')),
    ))),
    'default_currency' => env('ORANGE_CONSUMER_CURRENCY', 'EUR'),
];
