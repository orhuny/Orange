<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rng_seeds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('server_seed');
            $table->char('server_seed_hash', 64)->unique();
            $table->timestampTz('rotated_at')->nullable();
            $table->timestampTz('revealed_at')->nullable();
            $table->unsignedBigInteger('used_round_count')->default(0);
            $table->timestamps();

            $table->index('rotated_at');
            $table->index('revealed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rng_seeds');
    }
};
