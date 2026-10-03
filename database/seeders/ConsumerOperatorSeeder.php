<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Provisions the OrangeConsumer reference operator + mirror players.
 *
 * Mirrors the deterministic players defined in
 * OrangeConsumer/database/seeders/ConsumerPlayerSeeder.php so that wallet
 * callbacks dispatched against `external_player_id` resolve on both sides.
 *
 * See docs/orangeconsumer-integration.md.
 */
class ConsumerOperatorSeeder extends Seeder
{
    /**
     * Keep in sync with OrangeConsumer/ConsumerPlayerSeeder deterministic block.
     *
     * @var array<int, array{string, string, string}> [external_player_id, currency, country]
     */
    private const MIRRORED_PLAYERS = [
        ['ext_player_0001', 'EUR', 'DE'],
        ['ext_player_0002', 'EUR', 'DE'],
        ['ext_player_0003', 'USD', 'US'],
        ['ext_player_0004', 'USD', 'US'],
        ['ext_player_0005', 'TRY', 'TR'],
    ];

    public function run(): void
    {
        $slug = (string) config('orangeconsumer.operator_slug', 'orange-consumer');
        $secret = (string) config('orangeconsumer.secret_key');

        if ($secret === '') {
            $this->command?->warn(
                'ConsumerOperatorSeeder: ORANGE_CONSUMER_SECRET is empty — generating a one-shot secret. '
                .'Copy it into your .env (and OrangeConsumer .env as ORANGE_API_SECRET) before next run.',
            );
            $secret = Str::random(64);
            $this->command?->line('ORANGE_CONSUMER_SECRET='.$secret);
        }

        $operator = Operator::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => (string) config('orangeconsumer.operator_name', 'OrangeConsumer Reference Operator'),
                'status' => OperatorStatus::Active,
                'callback_base_url' => (string) config('orangeconsumer.callback_base_url'),
                'secret_key' => $secret,
                'ip_whitelist' => config('orangeconsumer.ip_whitelist'),
                'config' => [
                    'default_currency' => (string) config('orangeconsumer.default_currency', 'EUR'),
                ],
            ],
        );

        foreach (self::MIRRORED_PLAYERS as [$externalId, $currency, $country]) {
            Player::query()->updateOrCreate(
                [
                    'operator_id' => $operator->id,
                    'external_player_id' => $externalId,
                ],
                [
                    'currency' => $currency,
                    'country' => $country,
                    'status' => 'active',
                    'risk_score' => 0,
                ],
            );
        }

        $this->command?->info(sprintf(
            'OrangeConsumer operator ready: id=%s slug=%s players=%d',
            $operator->id,
            $operator->slug,
            count(self::MIRRORED_PLAYERS),
        ));
    }
}
