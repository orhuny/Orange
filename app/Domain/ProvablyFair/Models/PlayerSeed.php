<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Models;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's seed pair for instant games. The server seed stays secret (encrypted,
 * hidden) until the player rotates the pair; only its hash is shown before that.
 */
class PlayerSeed extends Model
{
    protected $fillable = [
        'operator_id',
        'player_id',
        'server_seed',
        'server_seed_hash',
        'client_seed',
        'nonce',
        'active',
        'revealed_at',
    ];

    protected $hidden = [
        'server_seed',
    ];

    protected function casts(): array
    {
        return [
            'server_seed' => 'encrypted',
            'nonce' => 'integer',
            'active' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }

    public function isRevealed(): bool
    {
        return $this->getAttribute('revealed_at') !== null;
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
}
