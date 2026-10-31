<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
        });

        AccessControl::sync();

        // Panel access used to be "email ends with @orange.local / @arkingroup.com".
        // Keep those people in by making them Super Admins; they can hand out
        // narrower roles from the Users screen afterwards.
        User::query()
            ->where(fn ($q) => $q->where('email', 'like', '%@orange.local')->orWhere('email', 'like', '%@arkingroup.com'))
            ->each(fn (User $user) => $user->assignRole(AccessControl::SUPER_ADMIN));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
