<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use App\Domain\Operator\Models\Operator;
use Database\Factories\OperatorGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A game as configured for one operator: enabled or not, launch settings, stricter limits. */
class OperatorGame extends Model
{
    /** @use HasFactory<OperatorGameFactory> */
    use HasFactory;

    protected $table = 'operator_game';

    protected $fillable = [
        'operator_id',
        'game_id',
        'enabled',
        'sort_order',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'sort_order' => 'integer',
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<OperatorGameLimit, $this> */
    public function limits(): HasMany
    {
        return $this->hasMany(OperatorGameLimit::class);
    }
}
