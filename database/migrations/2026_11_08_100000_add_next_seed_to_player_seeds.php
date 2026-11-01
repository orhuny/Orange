<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each seed pair carries the next server seed, committed (hashed) in advance: when the
 * player rotates and picks a client seed, the server seed it is paired with was already
 * fixed, so the server can't choose one against that client seed. Active pairs created
 * before this get their next seed now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_seeds', function (Blueprint $table) {
            $table->text('next_server_seed')->nullable()->after('server_seed_hash');
            $table->char('next_server_seed_hash', 64)->nullable()->unique()->after('next_server_seed');
        });

        DB::table('player_seeds')->where('active', true)->whereNull('next_server_seed')->orderBy('id')
            ->each(function (object $row): void {
                $next = bin2hex(random_bytes(32));
                DB::table('player_seeds')->where('id', $row->id)->update([
                    'next_server_seed' => Crypt::encryptString($next),
                    'next_server_seed_hash' => hash('sha256', $next),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('player_seeds', function (Blueprint $table) {
            $table->dropUnique(['next_server_seed_hash']);
            $table->dropColumn(['next_server_seed', 'next_server_seed_hash']);
        });
    }
};
