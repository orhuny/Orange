<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\GameCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A lobby category ("Crash", "Originals", ...), ordered by sort_order. */
class GameCategory extends Model
{
    /** @use HasFactory<GameCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Game, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'category_id');
    }
}
