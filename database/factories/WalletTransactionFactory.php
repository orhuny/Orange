<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        $operator = Operator::factory()->create();

        return [
            'player_id' => Player::factory()->state(['operator_id' => $operator->id]),
            'operator_id' => $operator->id,
            'bet_id' => null,
            'type' => WalletTransactionType::Debit,
            'amount' => '1.00000000',
            'currency' => 'EUR',
            'operator_callback_status' => WalletCallbackStatus::Mocked,
            'operator_response' => null,
            'idempotency_key' => 'wt-'.Str::lower(Str::random(12)),
        ];
    }
}
