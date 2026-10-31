<?php

declare(strict_types=1);

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator-scoped panel users: a user with an operator_id only ever sees that
 * operator's data; roles carry a scope so operator users only get operator roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('operator_id')->nullable()->after('id')
                ->constrained('operators')->cascadeOnDelete();
        });

        Schema::table(config('permission.table_names.roles'), function (Blueprint $table): void {
            $table->string('scope', 16)->default(AccessControl::SCOPE_PLATFORM)->after('guard_name');
        });

        AccessControl::sync();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operator_id');
        });

        Schema::table(config('permission.table_names.roles'), function (Blueprint $table): void {
            $table->dropColumn('scope');
        });
    }
};
