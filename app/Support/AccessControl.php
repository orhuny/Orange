<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for admin-panel permissions and the starter roles.
 *
 * Permissions are code-defined (each one guards something in the panel);
 * roles are data — the starter set below is only applied when a role is first
 * created, after that admins edit them from the Roles screen.
 */
final class AccessControl
{
    /** Has every permission implicitly (Gate::before); can't be edited or deleted. */
    public const SUPER_ADMIN = 'Super Admin';

    /** @var array<string, array<string, string>> group => [permission => label] */
    public const PERMISSIONS = [
        'Dashboard' => [
            'dashboard.view' => 'View dashboard KPIs and charts',
        ],
        'Operators' => [
            'operators.view' => 'View operators',
            'operators.manage' => 'Create, edit, suspend and delete operators',
        ],
        'Games' => [
            'games.view' => 'View games',
            'games.manage' => 'Create and edit games',
        ],
        'Game rounds' => [
            'rounds.view' => 'View game rounds',
        ],
        'Bets' => [
            'bets.view' => 'View bets',
            'bets.export' => 'Export bets',
        ],
        'Cashouts' => [
            'cashouts.view' => 'View cashouts',
        ],
        'Players' => [
            'players.view' => 'View players',
        ],
        'Webhooks' => [
            'webhooks.view' => 'View webhooks',
            'webhooks.redeliver' => 'Re-deliver failed webhooks',
        ],
        'RNG seeds' => [
            'rng_seeds.view' => 'View RNG seeds',
            'rng_seeds.manage' => 'Rotate and reveal RNG seeds',
        ],
        'Access control' => [
            'users.manage' => 'Create, edit and deactivate users',
            'roles.manage' => 'Create and edit roles and their permissions',
        ],
    ];

    /** @return array<string, list<string>> starter roles (role => permissions) */
    public static function starterRoles(): array
    {
        $all = array_keys(self::flat());
        $views = array_values(array_filter($all, fn (string $p) => str_ends_with($p, '.view')));

        return [
            self::SUPER_ADMIN => [],
            'Admin' => array_values(array_diff($all, ['users.manage', 'roles.manage'])),
            'Finance' => ['dashboard.view', 'operators.view', 'rounds.view', 'bets.view', 'bets.export', 'cashouts.view', 'players.view'],
            'Support' => ['dashboard.view', 'operators.view', 'rounds.view', 'bets.view', 'cashouts.view', 'players.view', 'webhooks.view', 'webhooks.redeliver'],
            'Viewer' => $views,
        ];
    }

    /** @return array<string, string> permission => label */
    public static function flat(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /**
     * Idempotent: creates missing permissions and starter roles. Existing roles
     * keep whatever permissions admins gave them.
     */
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (array_keys(self::flat()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (self::starterRoles() as $name => $permissions) {
            $role = Role::query()->where('name', $name)->where('guard_name', 'web')->first();
            if ($role === null) {
                Role::create(['name' => $name, 'guard_name' => 'web'])->syncPermissions($permissions);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->hasRole(self::SUPER_ADMIN);
    }
}
