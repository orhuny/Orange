<?php

declare(strict_types=1);

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/** Creates the lobby.* permissions on existing installs (fresh ones get them from sync()). */
return new class extends Migration
{
    public function up(): void
    {
        AccessControl::sync();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['lobby.view', 'lobby.manage'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
