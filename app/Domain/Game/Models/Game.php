<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\GameFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'name',
        'version',
        'rtp_target',
        'house_edge',
        'config',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'rtp_target' => 'float',
            'house_edge' => 'float',
            'config' => 'array',
            'version' => 'integer',
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

    /** @return HasMany<GameRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }
}
