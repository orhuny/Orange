<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Game\Enums\GameAccess;
use Database\Factories\GameFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * Tolerance for the rtp_target + house_edge == 1.0 invariant. Float
     * round-trips through the DB cast give ~1e-7 drift, so 1e-4 is well
     * inside the noise floor while still catching real misconfigurations.
     */
    private const RTP_INVARIANT_TOLERANCE = 0.0001;

    protected $fillable = [
        'code',
        'type',
        'slug',
        'category_id',
        'name',
        'thumbnail',
        'version',
        'rtp_target',
        'house_edge',
        'config',
        'status',
        'sort_order',
        'access',
    ];

    /** Same defaults as the columns, so a new model knows its type before it is saved. */
    protected $attributes = [
        'type' => 'crash',
        'access' => 'listed',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rtp_target' => 'float',
            'house_edge' => 'float',
            'config' => 'array',
            'version' => 'integer',
            'sort_order' => 'integer',
            'access' => GameAccess::class,
        ];
    }

    protected static function booted(): void
    {
        // rtp_target and house_edge drive the actual crash math via
        // ProvablyFairService::divisorForGame(). They MUST sum to 1.0 so the
        // admin-panel numbers stay truthful for audit / licensing review;
        // otherwise the math silently disagrees with the displayed RTP.
        static::saving(function (Game $game): void {
            $rtp = (float) $game->rtp_target;
            $edge = (float) $game->house_edge;

            if ($rtp <= 0.0 || $rtp >= 1.0) {
                throw new DomainException("rtp_target must be in (0, 1); got {$rtp}.");
            }

            if ($edge <= 0.0 || $edge >= 1.0) {
                throw new DomainException("house_edge must be in (0, 1); got {$edge}.");
            }

            if (abs(($rtp + $edge) - 1.0) > self::RTP_INVARIANT_TOLERANCE) {
                throw new DomainException(
                    "rtp_target + house_edge must equal 1.0; got {$rtp} + {$edge} = "
                    .($rtp + $edge).'.'
                );
            }
        });
    }

    /** The engine type that plays this game (games.type → config/games.php). */
    public function engineType(): string
    {
        $type = $this->getAttribute('type');

        // Never guess: a game loaded without its type column must not silently play as crash.
        if (! is_string($type) || $type === '') {
            throw new LogicException("Game [{$this->getAttribute('code')}] was loaded without its type.");
        }

        return $type;
    }

    public function isActive(): bool
    {
        return $this->getAttribute('status') === 'active';
    }

    /** @return BelongsTo<GameCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GameCategory::class, 'category_id');
    }

    /** @return HasMany<GameLimit, $this> */
    public function limits(): HasMany
    {
        return $this->hasMany(GameLimit::class);
    }

    /** @return HasMany<OperatorGame, $this> */
    public function operatorGames(): HasMany
    {
        return $this->hasMany(OperatorGame::class);
    }

    /** @return HasMany<GameRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }
}
