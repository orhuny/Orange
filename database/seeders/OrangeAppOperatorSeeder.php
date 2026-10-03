<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Provisions the OrangeApp operator (iOS app, virtual TKN currency).
 *
 * Unlike ConsumerOperatorSeeder no players are mirrored: OrangeApp users are
 * created on Orange by BetPlacementService on their first bet.
 *
 *   php artisan db:seed --class=OrangeAppOperatorSeeder
 */
class OrangeAppOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('orangeapp.operator_slug', 'orangeapp');
        $secret = (string) config('orangeapp.secret_key');

        if ($secret === '') {
            $this->command?->warn(
                'OrangeAppOperatorSeeder: ORANGE_APP_SECRET is empty — generating a one-shot secret. '
                .'Copy it into this .env and into OrangeApp/server/.env as ORANGE_API_SECRET.',
            );
            $secret = Str::random(64);
            $this->command?->line('ORANGE_APP_SECRET='.$secret);
        }

        $operator = Operator::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => (string) config('orangeapp.operator_name', 'OrangeApp (iOS, tokens)'),
                'status' => OperatorStatus::Active,
                'callback_base_url' => (string) config('orangeapp.callback_base_url'),
                'secret_key' => $secret,
                'ip_whitelist' => config('orangeapp.ip_whitelist'),
                'config' => [
                    'default_currency' => 'TKN',
                ],
            ],
        );

        $this->command?->info(sprintf(
            'OrangeApp operator ready: id=%s slug=%s — set ORANGE_OPERATOR_ID=%s in OrangeApp/server/.env',
            $operator->id,
            $operator->slug,
            $operator->id,
        ));
    }
}
