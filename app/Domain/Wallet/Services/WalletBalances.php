<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the games get the player's balance from. The operator's wallet owns the balance, and
 * every wallet call it answers carries balance_after: the balance right after that stake, win
 * or refund. The newest answer is the current balance. Nothing here adds or subtracts.
 */
final class WalletBalances
{
    /**
     * @param  CarbonInterface|null  $since  the start of the play session: only its own calls
     *                                       can make the balance pending
     */
    public function latest(string $operatorId, string $playerId, ?CarbonInterface $since = null): ReportedBalance
    {
        $tx = $this->forPlayer($operatorId, $playerId)
            ->whereNotNull('answered_at')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->first()
            // Answers recorded before answered_at existed.
            ?? $this->forPlayer($operatorId, $playerId)
                ->where('operator_callback_status', WalletCallbackStatus::Completed->value)
                ->latest('id')
                ->first();

        return new ReportedBalance(
            $tx === null ? null : self::balanceIn($tx),
            $tx === null ? null : (int) $tx->id,
            $tx === null ? null : self::answeredAt($tx),
            $since !== null && $this->unanswered($operatorId, $playerId, $since),
        );
    }

    /** The balance the answer to one wallet call carried, e.g. the one that took a bet's stake. */
    public function reportedBy(?int $txId): ?string
    {
        $tx = $txId === null ? null : WalletTransaction::query()->find($txId);

        return $tx instanceof WalletTransaction && $tx->getAttribute('operator_callback_status') === WalletCallbackStatus::Completed
            ? self::balanceIn($tx)
            : null;
    }

    /** A balance_after that is a plain decimal, as the wallet spelt it; null for anything else. */
    public static function balanceIn(WalletTransaction $tx): ?string
    {
        $balance = data_get($tx->getAttribute('operator_response'), 'body.balance_after');
        if (! is_string($balance) && ! is_int($balance) && ! is_float($balance)) {
            return null;
        }
        $balance = trim((string) $balance);

        return preg_match('/^-?\d+(\.\d+)?$/', $balance) === 1 ? $balance : null;
    }

    private static function answeredAt(WalletTransaction $tx): ?CarbonImmutable
    {
        $at = $tx->getAttribute('answered_at') ?? $tx->getAttribute('updated_at');

        return $at === null ? null : CarbonImmutable::parse($at);
    }

    /**
     * Any of the session's wallet calls the operator hasn't answered yet: still being sent, or
     * a win or refund that failed and is being re-sent. A refused stake is final, not pending.
     */
    private function unanswered(string $operatorId, string $playerId, CarbonInterface $since): bool
    {
        return $this->forPlayer($operatorId, $playerId)
            ->where('created_at', '>=', $since)
            ->where(fn (Builder $q) => $q
                ->where('operator_callback_status', WalletCallbackStatus::Pending->value)
                ->orWhere(fn (Builder $q) => $q
                    ->where('operator_callback_status', WalletCallbackStatus::Failed->value)
                    ->where('type', '!=', WalletTransactionType::Debit->value)))
            ->exists();
    }

    /** @return Builder<WalletTransaction> */
    private function forPlayer(string $operatorId, string $playerId): Builder
    {
        return WalletTransaction::query()->where('player_id', $playerId)->where('operator_id', $operatorId);
    }
}
