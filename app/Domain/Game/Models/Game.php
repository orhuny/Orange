<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

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

    /** @return HasMany<GameRound, $this> */
    public function rounds(): HasMany
    {
        return $this->hasMany(GameRound::class);
    }
}
