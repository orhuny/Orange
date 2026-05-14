<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Exceptions\WalletCallbackFailed;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function __construct(private readonly OperatorWalletClient $client) {}

    public function debit(
        Operator $operator,
        Player $player,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId = null,
    ): WalletTransaction {
        return $this->record(
            $operator,
            $player,
            WalletTransactionType::Debit,
            $amount,
            $currency,
            $idempotencyKey,
            $betId,
        );
    }

    public function credit(
        Operator $operator,
        Player $player,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId = null,
    ): WalletTransaction {
        return $this->record(
            $operator,
            $player,
            WalletTransactionType::Credit,
            $amount,
            $currency,
            $idempotencyKey,
            $betId,
        );
    }

    public function rollback(
        Operator $operator,
        Player $player,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId = null,
    ): WalletTransaction {
        return $this->record(
            $operator,
            $player,
            WalletTransactionType::Rollback,
            $amount,
            $currency,
            $idempotencyKey,
            $betId,
        );
    }

    private function record(
        Operator $operator,
        Player $player,
        WalletTransactionType $type,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId,
    ): WalletTransaction {
        $existing = WalletTransaction::query()
            ->where('operator_id', $operator->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof WalletTransaction) {
            return $existing;
        }

        $tx = WalletTransaction::create([
            'player_id' => $player->id,
            'operator_id' => $operator->id,
            'bet_id' => $betId,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'operator_callback_status' => WalletCallbackStatus::Pending,
            'operator_response' => null,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $result = $this->client->dispatch(
                $operator,
                $type,
                $amount,
                $currency,
                playerExternalId: $player->external_player_id,
                idempotencyKey: $idempotencyKey,
                reference: ['type' => $type->value, 'bet_id' => $betId],
            );

            $tx->forceFill([
                'operator_callback_status' => WalletCallbackStatus::Completed,
                'operator_response' => $result,
            ])->save();
        } catch (WalletCallbackFailed $e) {
            $tx->forceFill([
                'operator_callback_status' => WalletCallbackStatus::Failed,
                'operator_response' => $e->lastResponse ?? ['error' => $e->getMessage()],
            ])->save();

            Log::warning('wallet.callback.failed', [
                'operator_id' => $operator->id,
                'tx_id' => $tx->id,
                'type' => $type->value,
                'attempts' => $e->attempts,
                'error' => $e->getMessage(),
            ]);
        }

        return $tx->fresh() ?? $tx;
    }
}
