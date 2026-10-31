<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-player seed pairs for instant games: the server seed is committed (hashed) and
 * revealed when the player rotates; each bet takes the next nonce. Bets record the pair
 * and nonce they were played with, so every outcome can be replayed later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_seeds', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->text('server_seed');
            $table->char('server_seed_hash', 64)->unique();
            $table->string('client_seed', 64);
            $table->unsignedBigInteger('nonce')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampTz('revealed_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'active']);
        });

        // At most one active pair per player (partial index; PostgreSQL and SQLite both have them).
        DB::statement('CREATE UNIQUE INDEX player_seeds_one_active ON player_seeds (player_id) WHERE active');

        Schema::table('bets', function (Blueprint $table) {
            $table->foreignId('player_seed_id')->nullable()->after('round_id')->constrained('player_seeds');
            $table->unsignedBigInteger('nonce')->nullable()->after('player_seed_id');
        });
    }

    public function down(): void
    {
        Schema::table('bets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('player_seed_id');
            $table->dropColumn('nonce');
        });

        Schema::dropIfExists('player_seeds');
    }
};
