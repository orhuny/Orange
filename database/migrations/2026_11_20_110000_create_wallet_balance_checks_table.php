<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orange asking an operator's wallet for a player's balance (POST /wallet/balance), for
 * operators that answer it: when a play session opens a game, so the game starts from the
 * wallet's own figure. Every ask is kept, answered or not, so a balance shown can be traced
 * back to the answer it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_balance_checks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('operator_id');
            $table->uuid('player_id');
            $table->string('currency', 8);
            $table->string('status'); // answered | failed
            // As the wallet spelt it; null when it gave none (or no plain decimal).
            $table->string('balance', 40)->nullable();
            $table->json('response')->nullable();
            $table->timestamp('answered_at', 6)->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('operators')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->index(['player_id', 'answered_at'], 'wallet_balance_checks_player_answered_idx');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balance_checks');
    }
};
