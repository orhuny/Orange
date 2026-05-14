<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'external_player_id' => $this->faker->uuid(),
            'currency' => 'EUR',
            'country' => 'DE',
            'status' => 'active',
            'risk_score' => 0,
        ];
    }
}
