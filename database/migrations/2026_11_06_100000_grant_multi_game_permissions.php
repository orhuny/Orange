<?php

declare(strict_types=1);

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the multi-game permissions and gives the starter roles that already exist the
 * new permissions their definition now includes. Only adds: permissions admins removed
 * from a role stay removed, and custom roles are left alone.
 */
return new class extends Migration
{
    private const NEW = ['games.view', 'game_access.manage', 'game_limits.tighten', 'player_seeds.view', 'lobby.view', 'lobby.manage'];

    public function up(): void
    {
        AccessControl::sync();

        foreach (AccessControl::starterRoles() as $name => ['permissions' => $permissions]) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();
            $grant = array_values(array_intersect($permissions, self::NEW));
            if ($role instanceof Role && $grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // games.view and lobby.* existed or are owned by earlier migrations; only drop what this one introduced.
        Permission::query()->whereIn('name', ['game_access.manage', 'game_limits.tighten', 'player_seeds.view'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
