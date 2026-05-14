<?php

declare(strict_types=1);

namespace App\Domain\Operator\Models;

use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'operator_id',
        'external_player_id',
        'currency',
        'country',
        'status',
        'risk_score',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
        ];
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }
}
