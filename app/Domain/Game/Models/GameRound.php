<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Game\Enums\RoundState;
use App\Domain\ProvablyFair\Models\RngSeed;
use Database\Factories\GameRoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRound extends Model
{
    /** @use HasFactory<GameRoundFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
        'round_uuid',
        'state',
        'server_seed_id',
        'client_seed',
        'nonce',
        'crash_point',
        'betting_opened_at',
        'started_at',
        'crashed_at',
        'settled_at',
        'total_bet_amount',
        'total_payout_amount',
    ];

    protected function casts(): array
    {
        return [
            'state' => RoundState::class,
            'crash_point' => 'float',
            'betting_opened_at' => 'datetime',
            'started_at' => 'datetime',
            'crashed_at' => 'datetime',
            'settled_at' => 'datetime',
            'total_bet_amount' => 'decimal:8',
            'total_payout_amount' => 'decimal:8',
            'nonce' => 'integer',
        ];
    }

    /** States in which the crash point may be shown (it is stored from round start). */
    public const CRASH_POINT_PUBLIC_STATES = [RoundState::Crashed, RoundState::Settled];

    /**
     * The crash point is written when the round starts so the engine can time
     * the crash — it must never reach anyone (admin or player) before then.
     */
    public function publicCrashPoint(): ?float
    {
        return in_array($this->getAttribute('state'), self::CRASH_POINT_PUBLIC_STATES, true) ? $this->crash_point : null;
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<RngSeed, $this> */
    public function serverSeed(): BelongsTo
    {
        return $this->belongsTo(RngSeed::class, 'server_seed_id');
    }

    /** @return HasMany<Bet, $this> */
    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class, 'round_id');
    }
}
