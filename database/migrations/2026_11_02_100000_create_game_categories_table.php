<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lobby categories, managed and ordered from the admin panel. Seeded with the two
 * categories the first games use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('game_categories')->insert([
            ['slug' => 'crash', 'name' => 'Crash', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'originals', 'name' => 'Originals', 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('game_categories');
    }
};
