<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One time Orange asked an operator's wallet for a player's balance (WalletBalances::ask).
 * answered_at is a string with microseconds, like wallet_transactions.answered_at: it is
 * compared with the wallet calls' answers to find the newest balance.
 */
class WalletBalanceCheck extends Model
{
    public const ANSWERED = 'answered';

    public const FAILED = 'failed';

    protected $fillable = [
        'operator_id',
        'player_id',
        'currency',
        'status',
        'balance',
        'response',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
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
}
