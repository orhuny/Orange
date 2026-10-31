<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Seed retirement looks rounds up by seed (SeedRotator::retireFinished / reveal). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->index('server_seed_id');
        });
    }

    public function down(): void
    {
        Schema::table('game_rounds', function (Blueprint $table) {
            $table->dropIndex(['server_seed_id']);
        });
    }
};
