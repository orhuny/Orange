<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Game\Enums\RoundState;
use App\Domain\ProvablyFair\Models\RngSeed;
use Database\Factories\GameRoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
