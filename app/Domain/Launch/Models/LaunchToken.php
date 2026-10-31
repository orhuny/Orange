<?php

declare(strict_types=1);

namespace App\Domain\Launch\Models;

use App\Domain\Game\Models\Game;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single-use launch token (stored as a hash); game_id null launches the lobby. */
class LaunchToken extends Model
{
    protected $fillable = [
        'token_hash',
        'operator_id',
        'player_id',
        'game_id',
        'currency',
        'language',
        'return_url',
        'expires_at',
        'used_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
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

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
