<?php

declare(strict_types=1);

namespace App\Domain\Game\Models;

use Database\Factories\CashoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cashout extends Model
{
    /** @use HasFactory<CashoutFactory> */
    use HasFactory;

    protected $fillable = [
        'bet_id',
        'multiplier',
        'payout_amount',
        'cashed_out_at',
        'is_auto',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'payout_amount' => 'decimal:8',
            'cashed_out_at' => 'datetime',
            'is_auto' => 'boolean',
        ];
    }

    /** @return BelongsTo<Bet, $this> */
    public function bet(): BelongsTo
    {
        return $this->belongsTo(Bet::class);
    }
}
