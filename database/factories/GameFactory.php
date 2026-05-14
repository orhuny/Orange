<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'code' => 'crash-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => 'Crash '.$this->faker->word(),
            'version' => 1,
            'rtp_target' => 0.97,
            'house_edge' => 0.03,
            'config' => ['growth_rate' => 0.06, 'max_bet' => 1000, 'max_win' => 100000],
            'status' => 'active',
        ];
    }
}
