<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\GameLimitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A game's bet limits in one currency. */
class GameLimit extends Model
{
    /** @use HasFactory<GameLimitFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id',
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

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
