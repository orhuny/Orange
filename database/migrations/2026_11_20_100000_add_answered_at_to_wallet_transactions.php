<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the operator's wallet answered each call, to the microsecond. Its balance_after is the
 * player's balance as of then, so the newest answer is the balance the games show, even when
 * calls overlap (a crash win paid from the queue while the next bet is taken). The indexes
 * serve that lookup and the "anything still unanswered?" check, both per player.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->timestamp('answered_at', 6)->nullable();
            $table->index(['player_id', 'answered_at'], 'wallet_tx_player_answered_idx');
            $table->index(['player_id', 'created_at'], 'wallet_tx_player_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_tx_player_answered_idx');
            $table->dropIndex('wallet_tx_player_created_idx');
            $table->dropColumn('answered_at');
        });
    }
};
