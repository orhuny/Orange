<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\OperatorGame;
use App\Domain\Game\Models\OperatorGameLimit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorGameLimit>
 */
class OperatorGameLimitFactory extends Factory
{
    protected $model = OperatorGameLimit::class;

    public function definition(): array
    {
        return [
            'operator_game_id' => OperatorGame::factory(),
            'currency' => 'EUR',
            'min_bet' => null,
            'max_bet' => null,
            'max_win' => null,
        ];
    }
}
