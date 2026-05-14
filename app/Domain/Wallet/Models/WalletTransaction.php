<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Game\Models\Bet;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'player_id',
        'operator_id',
        'bet_id',
        'type',
        'amount',
        'currency',
        'operator_callback_status',
        'operator_response',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'operator_callback_status' => WalletCallbackStatus::class,
            'operator_response' => 'array',
            'amount' => 'decimal:8',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Bet, $this> */
    public function bet(): BelongsTo
    {
        return $this->belongsTo(Bet::class);
    }
}
