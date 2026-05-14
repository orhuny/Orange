<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bet_id')->unique();
            $table->decimal('multiplier', 10, 2);
            $table->decimal('payout_amount', 18, 8);
            $table->timestampTz('cashed_out_at');
            $table->boolean('is_auto')->default(false);
            $table->timestamps();

            $table->foreign('bet_id')->references('id')->on('bets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashouts');
    }
};
