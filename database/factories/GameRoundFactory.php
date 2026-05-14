<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\ProvablyFair\Models\RngSeed;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GameRound>
 */
class GameRoundFactory extends Factory
{
    protected $model = GameRound::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'round_uuid' => (string) Str::uuid(),
            'state' => RoundState::Waiting,
            'server_seed_id' => RngSeed::factory(),
            'client_seed' => 'house-client-seed',
            'nonce' => $this->faker->numberBetween(1, 1_000_000),
            'crash_point' => null,
            'betting_opened_at' => null,
            'started_at' => null,
            'crashed_at' => null,
            'settled_at' => null,
            'total_bet_amount' => 0,
            'total_payout_amount' => 0,
        ];
    }

    public function inState(RoundState $state): static
    {
        return $this->state(['state' => $state]);
    }
}
