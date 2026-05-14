<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cashout>
 */
class CashoutFactory extends Factory
{
    protected $model = Cashout::class;

    public function definition(): array
    {
        return [
            'bet_id' => Bet::factory(),
            'multiplier' => $this->faker->randomFloat(2, 1.0, 25.0),
            'payout_amount' => $this->faker->randomFloat(8, 1.0, 1000.0),
            'cashed_out_at' => now(),
            'is_auto' => false,
        ];
    }

    public function auto(): static
    {
        return $this->state(['is_auto' => true]);
    }
}
