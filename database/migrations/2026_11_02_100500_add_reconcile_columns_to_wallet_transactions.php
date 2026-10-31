<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** wallet:reconcile bookkeeping: how often a callback was re-sent and when it is due next. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedInteger('reconcile_attempts')->default(0);
            $table->timestampTz('next_reconcile_at')->nullable();
            $table->index(['operator_callback_status', 'next_reconcile_at'], 'wallet_tx_reconcile_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_reconcile_idx');
            $table->dropColumn(['reconcile_attempts', 'next_reconcile_at']);
        });
    }
};
