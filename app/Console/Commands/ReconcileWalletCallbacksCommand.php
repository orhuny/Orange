<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Wallet\Services\WalletReconciler;
use Illuminate\Console\Command;

class ReconcileWalletCallbacksCommand extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'Re-send credit and rollback callbacks the operator has not acknowledged (failed, or pending past the grace period), with backoff';

    public function handle(WalletReconciler $reconciler): int
    {
        $summary = $reconciler->run();

        $this->info(sprintf(
            'wallet:reconcile — %d attempted: %d completed, %d still owed, %d given up',
            $summary['attempted'],
            $summary['completed'],
            $summary['failed'],
            $summary['gave_up'],
        ));

        return self::SUCCESS;
    }
}
