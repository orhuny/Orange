<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
{
    protected $model = Bet::class;

    public function definition(): array
    {
        $operator = Operator::factory()->create();

        return [
            'round_id' => GameRound::factory(),
            'player_id' => Player::factory()->state(['operator_id' => $operator->id]),
            'operator_id' => $operator->id,
            'amount' => '1.00000000',
            'currency' => 'EUR',
            'status' => BetStatus::Placed,
            'auto_cashout_at' => null,
            'placed_at' => now(),
            'idempotency_key' => 'bet-'.Str::lower(Str::random(12)),
        ];
    }
}
