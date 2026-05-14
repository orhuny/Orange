<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Enums\WebhookStatus;
use App\Domain\Wallet\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'event' => WebhookEvent::BetPlaced,
            'payload' => ['demo' => true],
            'status' => WebhookStatus::Pending,
            'attempts' => 0,
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => WebhookStatus::Delivered,
            'delivered_at' => now(),
            'attempts' => 1,
        ]);
    }
}
