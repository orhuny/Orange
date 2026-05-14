<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_api_keys', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('operator_id');
            $table->string('key_id')->unique();
            $table->string('secret_hash');
            $table->json('scopes')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')
                ->references('id')->on('operators')
                ->cascadeOnDelete();

            $table->index(['operator_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_api_keys');
    }
};
