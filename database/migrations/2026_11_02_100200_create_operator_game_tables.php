<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which operator may play which game, and the per-currency limits.
 *
 * - operator_game: enable/disable a game for an operator, plus launch settings.
 * - game_limits: the game's limits per currency. A game with no rows accepts any
 *   currency without limits (only crash, until rows are added).
 * - operator_game_limits: an operator's stricter limits per currency (tighten only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_game', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['operator_id', 'game_id']);
            $table->index('game_id');
        });

        Schema::create('game_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->string('currency', 8);
            $table->decimal('min_bet', 18, 8);
            $table->decimal('max_bet', 18, 8);
            $table->decimal('max_win', 18, 8);
            $table->timestamps();

            $table->unique(['game_id', 'currency']);
        });

        Schema::create('operator_game_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operator_game_id')->constrained('operator_game')->cascadeOnDelete();
            $table->string('currency', 8);
            $table->decimal('min_bet', 18, 8)->nullable();
            $table->decimal('max_bet', 18, 8)->nullable();
            $table->decimal('max_win', 18, 8)->nullable();
            $table->timestamps();

            $table->unique(['operator_game_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_game_limits');
        Schema::dropIfExists('game_limits');
        Schema::dropIfExists('operator_game');
    }
};
