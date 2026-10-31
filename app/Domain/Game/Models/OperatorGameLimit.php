<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\OperatorGameLimitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator's own limits for a game in one currency. They can only tighten the
 * game's limits: GameLimits takes the stricter of the two, whatever is stored here.
 */
class OperatorGameLimit extends Model
{
    /** @use HasFactory<OperatorGameLimitFactory> */
    use HasFactory;

    protected $fillable = [
        'operator_game_id',
        'currency',
        'min_bet',
        'max_bet',
        'max_win',
    ];

    protected function casts(): array
    {
        return [
            'min_bet' => 'decimal:8',
            'max_bet' => 'decimal:8',
            'max_win' => 'decimal:8',
        ];
    }

    /** @return BelongsTo<OperatorGame, $this> */
    public function operatorGame(): BelongsTo
    {
        return $this->belongsTo(OperatorGame::class);
    }
}
