<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Exceptions\WalletCallbackFailed;
use App\Domain\Wallet\Models\WalletBalanceCheck;
use App\Domain\Wallet\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Where the games get the player's balance from. The operator's wallet owns the balance:
 * - every wallet call it answers carries balance_after, the balance right after that stake,
 *   win or refund;
 * - an operator that answers POST /wallet/balance is also asked when a game opens (ask()).
 *
 * The newest of those answers is the current balance. Nothing here adds or subtracts.
 */
final class WalletBalances
{
    public function __construct(private readonly OperatorWalletClient $client) {}

    /**
     * @param  CarbonInterface|null  $since  the start of the play session: only its own calls
     *                                       can make the balance pending
     */
    public function latest(string $operatorId, string $playerId, ?CarbonInterface $since = null): ReportedBalance
    {
        $tx = $this->transactions($operatorId, $playerId)
            ->whereNotNull('answered_at')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->first()
            // Answers recorded before answered_at existed.
            ?? $this->transactions($operatorId, $playerId)
                ->where('operator_callback_status', WalletCallbackStatus::Completed->value)
                ->latest('id')
                ->first();
        $check = WalletBalanceCheck::query()
            ->where('player_id', $playerId)
            ->where('operator_id', $operatorId)
            ->where('status', WalletBalanceCheck::ANSWERED)
            ->whereNotNull('balance')
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->first();
        $pending = $since !== null && $this->unanswered($operatorId, $playerId, $since);

        $txAt = $tx === null ? null : self::time($tx->getAttribute('answered_at') ?? $tx->getAttribute('updated_at'));
        $checkAt = $check === null ? null : self::time($check->getAttribute('answered_at'));

        // The wallet asked directly, when its answer is the newer one.
        if ($check !== null && $checkAt !== null && ($txAt === null || $checkAt->gte($txAt))) {
            return new ReportedBalance((string) $check->getAttribute('balance'), null, $checkAt, $pending, (int) $check->id);
        }

        return new ReportedBalance(
            $tx === null ? null : self::balanceIn($tx),
            $tx === null ? null : (int) $tx->id,
            $txAt,
            $pending,
        );
    }

    /**
     * Asks the operator's wallet for the player's balance now (POST /wallet/balance), if it
     * answers that (config wallet_balance). The ask is recorded either way; an answer is from
     * then on the newest balance latest() gives, until a wallet call reports a newer one.
     */
    public function ask(Operator $operator, Player $player, string $currency): ?WalletBalanceCheck
    {
        if (! $operator->answersWalletBalance()) {
            return null;
        }
        $row = ['operator_id' => $operator->id, 'player_id' => $player->id, 'currency' => $currency];

        try {
            $result = $this->client->balance($operator, (string) $player->external_player_id, $currency, WalletCallBudget::balance());
        } catch (WalletCallbackFailed $e) {
            Log::channel('operator')->warning('wallet.balance.unanswered', [
                'operator_id' => $operator->id,
                'player_id' => $player->id,
                'attempts' => $e->attempts,
                'error' => $e->getMessage(),
            ]);

            return WalletBalanceCheck::create($row + [
                'status' => WalletBalanceCheck::FAILED,
                'response' => ($e->lastResponse ?? ['error' => $e->getMessage()]) + ['attempts' => $e->attempts],
            ]);
        }

        // A balance in another currency than the one asked for is not this session's.
        $answeredCurrency = data_get($result, 'body.currency');
        $sameCurrency = ! is_string($answeredCurrency) || strtoupper($answeredCurrency) === strtoupper($currency);

        return WalletBalanceCheck::create($row + [
            'status' => WalletBalanceCheck::ANSWERED,
            'balance' => $sameCurrency ? self::decimal(data_get($result, 'body.balance')) : null,
            'response' => $result,
            // A string, not a datetime cast: Eloquent's cast would drop the microseconds.
            'answered_at' => now()->format('Y-m-d H:i:s.u'),
        ]);
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
        return self::decimal(data_get($tx->getAttribute('operator_response'), 'body.balance_after'));
    }

    private static function decimal(mixed $balance): ?string
    {
        if (! is_string($balance) && ! is_int($balance) && ! is_float($balance)) {
            return null;
        }
        $balance = trim((string) $balance);

        return preg_match('/^-?\d{1,20}(\.\d{1,12})?$/', $balance) === 1 ? $balance : null;
    }

    private static function time(mixed $at): ?CarbonImmutable
    {
        return $at === null || $at === '' ? null : CarbonImmutable::parse($at);
    }

    /**
     * Any of the session's wallet calls the operator hasn't answered yet: still being sent, or
     * a win or refund that failed and is being re-sent. A refused stake is final, not pending.
     */
    private function unanswered(string $operatorId, string $playerId, CarbonInterface $since): bool
    {
        return $this->transactions($operatorId, $playerId)
            ->where('created_at', '>=', $since)
            ->where(fn (Builder $q) => $q
                ->where('operator_callback_status', WalletCallbackStatus::Pending->value)
                ->orWhere(fn (Builder $q) => $q
                    ->where('operator_callback_status', WalletCallbackStatus::Failed->value)
                    ->where('type', '!=', WalletTransactionType::Debit->value)))
            ->exists();
    }

    /** @return Builder<WalletTransaction> */
    private function transactions(string $operatorId, string $playerId): Builder
    {
        return WalletTransaction::query()->where('player_id', $playerId)->where('operator_id', $operatorId);
    }
}
