<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bets for any game: game_id (backfilled from the round), round_id optional, the
 * player's choices (params), the resolved outcome, and the settlement (multiplier,
 * payout, settled_at — backfilled for crash from cashouts / the round's crash), plus
 * the max payout fixed when the bet was placed.
 */
return new class extends Migration
{
    private const CHUNK = 10_000;

    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->after('id')->constrained('games')->cascadeOnDelete();
            $table->json('params')->nullable()->after('auto_cashout_at');
            $table->json('outcome')->nullable()->after('params');
            $table->decimal('multiplier', 14, 4)->nullable()->after('outcome');
            $table->decimal('payout_amount', 18, 8)->nullable()->after('multiplier');
            // Effective max win when the bet was placed; null = uncapped.
            $table->decimal('max_payout', 18, 8)->nullable()->after('payout_amount');
            $table->timestampTz('settled_at')->nullable()->after('placed_at');
        });

        $maxId = (int) DB::table('bets')->max('id');
        for ($from = 0; $from < $maxId; $from += self::CHUNK) {
            $range = [$from, $from + self::CHUNK];

            DB::update('UPDATE bets SET game_id = (SELECT game_rounds.game_id FROM game_rounds WHERE game_rounds.id = bets.round_id)
                WHERE game_id IS NULL AND id > ? AND id <= ?', $range);

            DB::update("UPDATE bets SET
                    multiplier = (SELECT cashouts.multiplier FROM cashouts WHERE cashouts.bet_id = bets.id),
                    payout_amount = (SELECT cashouts.payout_amount FROM cashouts WHERE cashouts.bet_id = bets.id),
                    settled_at = (SELECT cashouts.cashed_out_at FROM cashouts WHERE cashouts.bet_id = bets.id)
                WHERE status = 'won' AND id > ? AND id <= ?", $range);

            DB::update("UPDATE bets SET multiplier = 0, payout_amount = 0,
                    settled_at = (SELECT game_rounds.crashed_at FROM game_rounds WHERE game_rounds.id = bets.round_id)
                WHERE status = 'lost' AND id > ? AND id <= ?", $range);
        }

        Schema::table('bets', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable(false)->change();
            $table->unsignedBigInteger('round_id')->nullable()->change();
            $table->index(['game_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        // Rolling back would mean deleting real bets that have no round (instant
        // games); refuse instead of losing money records.
        if (DB::table('bets')->whereNull('round_id')->exists()) {
            throw new RuntimeException('Cannot roll back: bets without a round exist (instant games). Remove or archive them first.');
        }

        Schema::table('bets', function (Blueprint $table) {
            $table->dropIndex(['game_id', 'placed_at']);
            $table->dropConstrainedForeignId('game_id');
            $table->dropColumn(['params', 'outcome', 'multiplier', 'payout_amount', 'max_payout', 'settled_at']);
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->unsignedBigInteger('round_id')->nullable(false)->change();
        });
    }
};
