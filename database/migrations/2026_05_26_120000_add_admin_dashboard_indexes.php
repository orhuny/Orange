<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GlobalKpiWidget sums `cashouts.payout_amount` filtered by
        // `cashed_out_at >= today` every 15s. Without an index this scans
        // the whole cashouts table.
        Schema::table('cashouts', function (Blueprint $table): void {
            $table->index('cashed_out_at', 'cashouts_cashed_out_at_idx');
        });

        // TodayRoundsChart + KPI's "rounds today" count both filter
        // game_rounds by `created_at >= since` and a state IN list. The
        // existing (game_id, created_at) composite can't serve queries that
        // don't constrain game_id, so PG would fall back to a seq scan.
        // (created_at, state) lets the planner do a range scan + filter.
        Schema::table('game_rounds', function (Blueprint $table): void {
            $table->index(['created_at', 'state'], 'game_rounds_created_at_state_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cashouts', function (Blueprint $table): void {
            $table->dropIndex('cashouts_cashed_out_at_idx');
        });
        Schema::table('game_rounds', function (Blueprint $table): void {
            $table->dropIndex('game_rounds_created_at_state_idx');
        });
    }
};
