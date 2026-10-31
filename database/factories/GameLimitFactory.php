<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameLimit>
 */
class GameLimitFactory extends Factory
{
    protected $model = GameLimit::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'currency' => 'EUR',
            'min_bet' => '0.10000000',
            'max_bet' => '100.00000000',
            'max_win' => '10000.00000000',
        ];
    }
}
