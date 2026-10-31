<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue columns. Every existing game is the crash game: type crash, slug = code,
 * the crash category, and open to every operator (access=all) so integrations that
 * play it today keep working, including operators created later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('type', 32)->default('crash')->after('code');
            $table->string('slug')->nullable()->after('type');
            $table->foreignId('category_id')->nullable()->after('slug')->constrained('game_categories')->nullOnDelete();
            $table->string('thumbnail')->nullable()->after('name');
            $table->integer('sort_order')->default(0)->after('status');
            // all = every operator unless disabled for it; listed = only operators it is enabled for.
            $table->string('access', 16)->default('listed')->after('sort_order');
            $table->index('type');
        });

        $crash = DB::table('game_categories')->where('slug', 'crash')->value('id');
        DB::table('games')->update([
            'slug' => DB::raw('code'),
            'category_id' => $crash,
            'access' => 'all',
        ]);

        Schema::table('games', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['type']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['type', 'slug', 'thumbnail', 'sort_order', 'access']);
        });
    }
};
