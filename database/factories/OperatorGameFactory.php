<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\OperatorGame;
use App\Domain\Operator\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatorGame>
 */
class OperatorGameFactory extends Factory
{
    protected $model = OperatorGame::class;

    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'game_id' => Game::factory(),
            'enabled' => true,
            'sort_order' => null,
            'settings' => null,
        ];
    }

    /** Played directly through Orange's launch / lobby pages. */
    public function direct(): static
    {
        return $this->state(['settings' => ['play_mode' => 'direct']]);
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }
}
