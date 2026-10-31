<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\ProvablyFair\Models\PlayerSeed;
use Database\Factories\BetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Bet extends Model
{
    /** @use HasFactory<BetFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'round_id',
        'player_seed_id',
        'nonce',
        'player_id',
        'operator_id',
        'amount',
        'currency',
        'status',
        'auto_cashout_at',
        'params',
        'outcome',
        'multiplier',
        'payout_amount',
        'max_payout',
        'placed_at',
        'settled_at',
        'idempotency_key',
        'external_tx_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => BetStatus::class,
            'amount' => 'decimal:8',
            'auto_cashout_at' => 'decimal:2',
            'nonce' => 'integer',
            'params' => 'array',
            'outcome' => 'array',
            'multiplier' => 'decimal:4',
            'payout_amount' => 'decimal:8',
            'max_payout' => 'decimal:8',
            'placed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The outcome is written once, when the bet resolves; it is never rewritten.
        static::updating(function (Bet $bet): void {
            if ($bet->isDirty('outcome') && $bet->getRawOriginal('outcome') !== null) {
                throw new LogicException("Bet #{$bet->id} already has an outcome.");
            }
        });
    }

    /** @return BelongsTo<PlayerSeed, $this> */
    public function playerSeed(): BelongsTo
    {
        return $this->belongsTo(PlayerSeed::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
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
