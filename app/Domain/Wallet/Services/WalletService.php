<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;
use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Exceptions\WalletCallbackFailed;
use App\Domain\Wallet\Jobs\DispatchWalletCallbackJob;
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

    /**
     * Direct play: debits the stake and waits for the operator's answer, so a bet only
     * counts once its stake is confirmed. One attempt, no retries: the caller rolls back
     * a debit that got no definite answer instead of guessing.
     */
    public function debitNow(
        Operator $operator,
        Player $player,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId = null,
    ): DebitResult {
        $existing = WalletTransaction::query()
            ->where('operator_id', $operator->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing instanceof WalletTransaction) {
            return $this->debitResultFor($existing);
        }

        $tx = WalletTransaction::create([
            'player_id' => $player->id,
            'operator_id' => $operator->id,
            'bet_id' => $betId,
            'type' => WalletTransactionType::Debit,
            'amount' => $amount,
            'currency' => $currency,
            'operator_callback_status' => WalletCallbackStatus::Pending,
            'operator_response' => null,
            'idempotency_key' => $idempotencyKey,
        ]);
        $tx->setRelation('player', $player);
        $payload = $this->payloadFor($tx);

        try {
            $result = $this->client->dispatch(
                $operator,
                $payload['type'],
                $payload['amount'],
                $payload['currency'],
                playerExternalId: $payload['player_external_id'],
                idempotencyKey: $payload['idempotency_key'],
                reference: $payload['reference'],
                maxAttempts: 1,
            );
            $tx->forceFill(['operator_callback_status' => WalletCallbackStatus::Completed, 'operator_response' => $result])->save();
        } catch (WalletCallbackFailed $e) {
            $tx->forceFill([
                'operator_callback_status' => WalletCallbackStatus::Failed,
                // attempts=0: nothing was sent (no callback URL), which is a definite "no".
                'operator_response' => ($e->lastResponse ?? ['error' => $e->getMessage()]) + ['attempts' => $e->attempts],
            ])->save();
        }

        return $this->debitResultFor($tx);
    }

    private function debitResultFor(WalletTransaction $tx): DebitResult
    {
        $response = (array) $tx->getAttribute('operator_response');
        $balance = data_get($response, 'body.balance_after');
        $balance = is_scalar($balance) ? (string) $balance : null;

        if ($tx->getAttribute('operator_callback_status') === WalletCallbackStatus::Completed) {
            return new DebitResult($tx, DebitResult::ACCEPTED, balance: $balance);
        }

        $status = (int) ($response['status'] ?? 0);
        $error = data_get($response, 'body.error');
        $definite = ($status >= 400 && $status < 500) || ($response['attempts'] ?? null) === 0;

        return new DebitResult($tx, $definite ? DebitResult::REJECTED : DebitResult::UNKNOWN, is_string($error) ? $error : null);
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

    /**
     * @param  int  $delaySeconds  wait before sending: a rollback for a debit that got no answer
     *                             must not overtake that debit at the operator
     */
    public function rollback(
        Operator $operator,
        Player $player,
        string $amount,
        string $currency,
        string $idempotencyKey,
        ?int $betId = null,
        int $delaySeconds = 0,
    ): WalletTransaction {
        return $this->record(
            $operator,
            $player,
            WalletTransactionType::Rollback,
            $amount,
            $currency,
            $idempotencyKey,
            $betId,
            $delaySeconds,
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
        int $delaySeconds = 0,
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
        $tx->setRelation('player', $player);

        // Capture the params we need for the actual HTTP callback. Defer the
        // call to after the response is flushed so we don't pin the HTTP
        // worker (especially relevant under `php artisan serve` which is
        // single-process — a synchronous callback could deadlock with the
        // operator's own single-process server while the bet is still in
        // flight).
        $payload = $this->payloadFor($tx);

        // Always go through the queue. With a real worker the outbound
        // HTTP call runs out-of-band so nothing in this process blocks on
        // it — critical for the crash:tick daemon, where AutoCashoutOnTick
        // calls credit() from inside the per-tick loop. An inline callback
        // there freezes the entire tick stream for the operator's
        // timeout × retry budget (seconds), which appears in the browser
        // as the multiplier locking up the moment auto-cashout fires.
        //
        // Sent only after the surrounding transaction commits: a worker must never pick
        // up a callback for a row (or a bet status) that may still be rolled back.
        //
        // Tests run with queue.default=sync, so DispatchWalletCallbackJob
        // executes as soon as the transaction commits (immediately outside one)
        // and the row is updated before fresh() reads it back.
        $dispatch = DispatchWalletCallbackJob::dispatch($payload)->afterCommit();
        if ($delaySeconds > 0) {
            $dispatch->delay(now()->addSeconds($delaySeconds));
        }
        unset($dispatch);

        return $tx->fresh() ?? $tx;
    }

    /**
     * The callback payload for a transaction, built only here so that the first send
     * (record) and every wallet:reconcile retry carry the same tx_id, amount and
     * reference: the operator deduplicates on tx_id and must see the same request.
     *
     * @return array<string, mixed>
     */
    public function payloadFor(WalletTransaction $tx): array
    {
        /** @var WalletTransactionType $type */
        $type = $tx->getAttribute('type');

        return [
            'operator_id' => $tx->operator_id,
            'tx_id' => (int) $tx->id,
            'type' => $type,
            'amount' => $this->callbackAmount($tx),
            'currency' => $tx->currency,
            'player_external_id' => $tx->player->external_player_id,
            'idempotency_key' => $tx->idempotency_key,
            'reference' => $this->referenceFor($tx, $type),
        ];
    }

    /**
     * What the callback is about. A rollback also names the debit it reverses
     * (debit_tx_id), so an operator that hasn't seen that debit yet can refuse it when it
     * arrives late instead of taking the stake after the refund was acknowledged.
     *
     * @return array<string, mixed>
     */
    private function referenceFor(WalletTransaction $tx, WalletTransactionType $type): array
    {
        $reference = ['type' => $type->value, 'bet_id' => $tx->bet_id === null ? null : (int) $tx->bet_id];

        $betKey = $type === WalletTransactionType::Rollback ? $tx->bet?->idempotency_key : null;
        if (is_string($betKey)) {
            $reference['debit_tx_id'] = "bet:{$betKey}";
        }

        return $reference;
    }

    /**
     * Sends the callback for an existing transaction again, in this process. Used by
     * wallet:reconcile, which must not depend on the queue whose job may have been lost.
     */
    public function resend(WalletTransaction $tx): void
    {
        $this->dispatchCallback($this->payloadFor($tx));
    }

    /**
     * A debit goes out with the amount exactly as the bet request spelt it ("1.00"; the
     * afq contract pins the body) and is only ever sent once, from record(). Credits and
     * rollbacks go out at the column's 8-dp scale, which is what their callers already
     * pass, so a retry rebuilt from the database is byte-identical to the first send.
     */
    private function callbackAmount(WalletTransaction $tx): string
    {
        $raw = $tx->getAttributes()['amount'] ?? null;

        if ($tx->getAttribute('type') === WalletTransactionType::Debit && is_string($raw)) {
            return $raw;
        }

        return (string) $tx->amount;
    }

    /**
     * Executes the actual HTTP callback to the operator and updates the
     * matching wallet_transactions row. Public so the after-response
     * closure can call back into it via the container.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatchCallback(array $payload): void
    {
        /** @var WalletTransaction|null $tx */
        $tx = WalletTransaction::find($payload['tx_id']);
        if (! $tx) {
            return;
        }

        $operator = Operator::find($payload['operator_id']);
        if (! $operator) {
            $tx->forceFill([
                'operator_callback_status' => WalletCallbackStatus::Failed,
                'operator_response' => ['error' => 'operator_not_found'],
            ])->save();

            return;
        }

        try {
            $result = $this->client->dispatch(
                $operator,
                $payload['type'],
                $payload['amount'],
                $payload['currency'],
                playerExternalId: $payload['player_external_id'],
                idempotencyKey: $payload['idempotency_key'],
                reference: $payload['reference'],
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

            Log::channel('operator')->warning('wallet.callback.failed', [
                'operator_id' => $operator->id,
                'tx_id' => $tx->id,
                'type' => $payload['type']->value,
                'attempts' => $e->attempts,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
