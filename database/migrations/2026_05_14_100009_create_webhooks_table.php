<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('operator_id');
            $table->string('event');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_retry_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->json('last_response')->nullable();
            $table->timestamps();

            $table->foreign('operator_id')->references('id')->on('operators')->cascadeOnDelete();
            $table->index(['status', 'next_retry_at']);
            $table->index('operator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
