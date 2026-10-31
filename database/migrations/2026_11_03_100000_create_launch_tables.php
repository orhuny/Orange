<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Launching a player into a game or the lobby.
 *
 * - launch_tokens: short-lived, single-use tokens an operator gets from
 *   /v1/games/{code}/launch or /v1/lobby/launch (game_id null = lobby).
 * - play_sessions: what a token is exchanged for when the page opens.
 *
 * Only SHA-256 hashes of tokens are stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('launch_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignUuid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->string('currency', 8);
            $table->string('language', 16)->nullable();
            $table->string('return_url', 2048)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('play_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('token_hash', 64)->unique();
            $table->foreignUuid('operator_id')->constrained('operators')->cascadeOnDelete();
            $table->foreignUuid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('game_id')->nullable()->constrained('games')->cascadeOnDelete();
            $table->boolean('lobby')->default(false);
            $table->string('currency', 8);
            $table->string('language', 16)->nullable();
            $table->string('return_url', 2048)->nullable();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_sessions');
        Schema::dropIfExists('launch_tokens');
    }
};
