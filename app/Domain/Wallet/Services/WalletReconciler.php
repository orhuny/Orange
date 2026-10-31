<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Wallet\Enums\WalletCallbackStatus;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-sends credits (wins) and rollbacks (refunds) the operator has not acknowledged,
 * with backoff, until it does or the attempts run out. Debits are never re-sent: a
 * failed debit is final and the bet flow deals with it.
 */
final class WalletReconciler
{
    public function __construct(private readonly WalletService $wallet) {}

    /** @return array{attempted: int, completed: int, failed: int, gave_up: int} */
    public function run(): array
    {
        $maxAttempts = (int) config('wallet.reconcile.max_attempts', 12);
        $backoff = array_values(array_map('intval', (array) config('wallet.reconcile.backoff_seconds', [60])));
        $grace = (int) config('wallet.reconcile.pending_grace_seconds', 300);
        $now = now();

        $ids = WalletTransaction::query()
            ->whereIn('type', [WalletTransactionType::Credit->value, WalletTransactionType::Rollback->value])
            ->where('reconcile_attempts', '<', $maxAttempts)
            ->where(fn (Builder $q) => $q
                ->where('operator_callback_status', WalletCallbackStatus::Failed->value)
                // Pending past the grace period: its queued job was most likely lost.
                ->orWhere(fn (Builder $q) => $q
                    ->where('operator_callback_status', WalletCallbackStatus::Pending->value)
                    ->where('created_at', '<=', $now->copy()->subSeconds($grace))))
            ->where(fn (Builder $q) => $q->whereNull('next_reconcile_at')->orWhere('next_reconcile_at', '<=', $now))
            ->orderBy('id')
            ->limit((int) config('wallet.reconcile.batch_size', 100))
            ->pluck('id');

        $summary = ['attempted' => 0, 'completed' => 0, 'failed' => 0, 'gave_up' => 0];

        foreach ($ids as $id) {
            $tx = WalletTransaction::query()->find($id);
            if (! $tx instanceof WalletTransaction) {
                continue;
            }

            // Claim the row: only the run that bumps the attempt count it read sends it,
            // so overlapping runs never send the same callback twice at once.
            $attempt = $tx->reconcile_attempts + 1;
            $claimed = DB::table('wallet_transactions')
                ->where('id', $tx->id)
                ->where('reconcile_attempts', $tx->reconcile_attempts)
                ->update([
                    'reconcile_attempts' => $attempt,
                    'next_reconcile_at' => $now->copy()->addSeconds($backoff[min($attempt, count($backoff)) - 1] ?? 60),
                    'updated_at' => now(),
                ]);
            if ($claimed === 0) {
                continue;
            }

            $summary['attempted']++;
            $this->wallet->resend($tx);

            if ($tx->fresh()?->getAttribute('operator_callback_status') === WalletCallbackStatus::Completed) {
                $summary['completed']++;

                continue;
            }

            $summary['failed']++;
            if ($attempt >= $maxAttempts) {
                $summary['gave_up']++;
                Log::channel('operator')->critical('wallet.reconcile.gave_up', [
                    'tx_id' => $tx->id,
                    'operator_id' => $tx->operator_id,
                    'type' => $tx->getAttribute('type')?->value,
                    'amount' => (string) $tx->amount,
                    'currency' => $tx->currency,
                    'idempotency_key' => $tx->idempotency_key,
                    'attempts' => $attempt,
                ]);
            }
        }

        return $summary;
    }
}
