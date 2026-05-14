<?php

declare(strict_types=1);

return [
    'house_client_seed' => env('PROVABLY_FAIR_HOUSE_CLIENT_SEED', 'orange-house-seed-rotate-me'),
    'seed_rotation_rounds' => (int) env('PROVABLY_FAIR_SEED_ROTATION_ROUNDS', 1000),
];
