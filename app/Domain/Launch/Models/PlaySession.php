<?php

declare(strict_types=1);

namespace App\Domain\Launch\Models;

use App\Domain\Game\Models\Game;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's authenticated session on a game page or the lobby, created from a launch
 * token. The page holds the session's bearer token in memory (no cookies: third-party
 * iframe cookies are blocked or partitioned).
 */
class PlaySession extends Model
{
    use HasUuids;

    protected $fillable = [
        'token_hash',
        'operator_id',
        'player_id',
        'game_id',
        'lobby',
        'currency',
        'language',
        'return_url',
        'expires_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'lobby' => 'boolean',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');

        return $this->getAttribute('revoked_at') === null && $expiresAt instanceof CarbonInterface && $expiresAt->isFuture();
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
