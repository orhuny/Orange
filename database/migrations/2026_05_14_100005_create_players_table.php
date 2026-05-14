<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operator_id');
            $table->string('external_player_id');
            $table->string('currency', 8);
            $table->string('country', 2)->nullable();
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('operators')->cascadeOnDelete();
            $table->unique(['operator_id', 'external_player_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
