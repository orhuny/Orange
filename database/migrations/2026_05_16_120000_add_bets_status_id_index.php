<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Filament's bets list defaults to ORDER BY id DESC and the status filter is
 * the most common WHERE. A composite (status, id desc) index lets Postgres
 * answer "newest N bets where status=X" without re-sorting after the
 * single-column status index is consulted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bets', function (Blueprint $table): void {
            $table->index(['status', 'id'], 'bets_status_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table): void {
            $table->dropIndex('bets_status_id_idx');
        });
    }
};
