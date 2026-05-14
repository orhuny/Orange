<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('round_id');
            $table->uuid('player_id');
            $table->uuid('operator_id');
            $table->decimal('amount', 18, 8);
            $table->string('currency', 8);
            $table->string('status')->default('placed')->index();
            $table->decimal('auto_cashout_at', 10, 2)->nullable();
            $table->timestampTz('placed_at');
            $table->string('idempotency_key');
            $table->string('external_tx_id')->nullable();
            $table->timestamps();

            $table->foreign('round_id')->references('id')->on('game_rounds')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('operators')->cascadeOnDelete();
            $table->unique(['operator_id', 'idempotency_key']);
            $table->index('round_id');
            $table->index('player_id');
            $table->index('placed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bets');
    }
};
