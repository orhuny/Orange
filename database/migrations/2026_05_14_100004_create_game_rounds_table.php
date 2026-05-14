<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rounds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('game_id');
            $table->uuid('round_uuid')->unique();
            $table->string('state')->index();
            $table->unsignedBigInteger('server_seed_id');
            $table->string('client_seed');
            $table->unsignedBigInteger('nonce');
            $table->decimal('crash_point', 10, 2)->nullable();
            $table->timestampTz('betting_opened_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('crashed_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->decimal('total_bet_amount', 18, 8)->default(0);
            $table->decimal('total_payout_amount', 18, 8)->default(0);
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->foreign('server_seed_id')->references('id')->on('rng_seeds');
            $table->index(['game_id', 'created_at']);
            $table->index(['game_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
    }
};
