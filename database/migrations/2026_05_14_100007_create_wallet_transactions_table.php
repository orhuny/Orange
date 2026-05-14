<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('player_id');
            $table->uuid('operator_id');
            $table->unsignedBigInteger('bet_id')->nullable();
            $table->string('type'); // debit | credit | rollback
            $table->decimal('amount', 18, 8);
            $table->string('currency', 8);
            $table->string('operator_callback_status')->default('pending');
            $table->json('operator_response')->nullable();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('operators')->cascadeOnDelete();
            $table->foreign('bet_id')->references('id')->on('bets')->nullOnDelete();
            $table->unique(['operator_id', 'idempotency_key']);
            $table->index('bet_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
