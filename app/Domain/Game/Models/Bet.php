<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Database\Factories\BetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bet extends Model
{
    /** @use HasFactory<BetFactory> */
    use HasFactory;

    protected $fillable = [
        'round_id',
        'player_id',
        'operator_id',
        'amount',
        'currency',
        'status',
        'auto_cashout_at',
        'placed_at',
        'idempotency_key',
        'external_tx_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => BetStatus::class,
            'amount' => 'decimal:8',
            'auto_cashout_at' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GameRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(GameRound::class, 'round_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return HasOne<Cashout, $this> */
    public function cashout(): HasOne
    {
        return $this->hasOne(Cashout::class);
    }
}
