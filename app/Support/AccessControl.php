<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single source of truth for admin-panel permissions and the starter roles.
 *
 * Two kinds of panel user:
 *  - platform users (operator_id null): our staff, platform-scoped roles.
 *  - operator users (operator_id set): an integrator's staff. Every report is
 *    filtered to their operator and they can only ever hold OPERATOR_PERMISSIONS,
 *    whatever role they are given (enforced in Gate::before).
 *
 * Permissions are code-defined; roles are data — the starter set below is only
 * applied when a role is first created, after that admins edit them.
 */
final class AccessControl
{
    /** Has every permission implicitly (Gate::before); can't be edited or deleted. */
    public const SUPER_ADMIN = 'Super Admin';

    public const SCOPE_PLATFORM = 'platform';

    public const SCOPE_OPERATOR = 'operator';

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

    /** The most an operator user can ever do — always limited to their own operator's data. */
    public const OPERATOR_PERMISSIONS = [
        'dashboard.view',
        'operators.view',
        'rounds.view',
        'bets.view',
        'bets.export',
        'cashouts.view',
        'players.view',
        'webhooks.view',
        'webhooks.redeliver',
        'users.manage',
    ];

    /** @return array<string, array{scope: string, permissions: list<string>}> */
    public static function starterRoles(): array
    {
        $all = array_keys(self::flat());
        $views = array_values(array_filter($all, fn (string $p) => str_ends_with($p, '.view')));
        $platform = fn (array $permissions) => ['scope' => self::SCOPE_PLATFORM, 'permissions' => $permissions];
        $operator = fn (array $permissions) => ['scope' => self::SCOPE_OPERATOR, 'permissions' => $permissions];

        return [
            self::SUPER_ADMIN => $platform([]),
            'Admin' => $platform(array_values(array_diff($all, ['users.manage', 'roles.manage']))),
            'Finance' => $platform(['dashboard.view', 'operators.view', 'rounds.view', 'bets.view', 'bets.export', 'cashouts.view', 'players.view']),
            'Support' => $platform(['dashboard.view', 'operators.view', 'rounds.view', 'bets.view', 'cashouts.view', 'players.view', 'webhooks.view', 'webhooks.redeliver']),
            'Viewer' => $platform($views),
            'Operator Admin' => $operator(self::OPERATOR_PERMISSIONS),
            'Operator Viewer' => $operator(['dashboard.view', 'operators.view', 'rounds.view', 'bets.view', 'bets.export', 'cashouts.view', 'players.view', 'webhooks.view']),
        ];
    }

    /** @return array<string, string> permission => label */
    public static function flat(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /** @return list<string> permissions a role of the given scope may hold */
    public static function permissionsForScope(string $scope): array
    {
        return $scope === self::SCOPE_OPERATOR ? self::OPERATOR_PERMISSIONS : array_keys(self::flat());
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

        // Operator roles need the scope column (added by a later migration).
        $hasScope = Schema::hasColumn(config('permission.table_names.roles'), 'scope');

        foreach (self::starterRoles() as $name => ['scope' => $scope, 'permissions' => $permissions]) {
            if (! $hasScope && $scope !== self::SCOPE_PLATFORM) {
                continue;
            }
            if (Role::query()->where('name', $name)->where('guard_name', 'web')->exists()) {
                continue;
            }
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            if ($hasScope) {
                // forceFill: Eloquent caches a model's guardable columns on first fill, so
                // when an earlier migration created roles before `scope` existed, a plain
                // mass-assign would silently drop it.
                $role->forceFill(['scope' => $scope])->save();
            }
            $role->syncPermissions($permissions);
        }

        $registrar->forgetCachedPermissions();
    }

    public static function isSuperAdmin(?User $user): bool
    {
        return $user !== null && $user->operator_id === null && $user->hasRole(self::SUPER_ADMIN);
    }

    /** The operator the signed-in user is limited to; null for platform users. */
    public static function operatorId(): ?string
    {
        $user = auth()->user();

        return $user instanceof User ? $user->operator_id : null;
    }

    /**
     * Limits a query to the signed-in operator user's data; platform users see
     * everything. $column may be a relation path, e.g. "bet.operator_id".
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scopeToOperator(Builder $query, string $column = 'operator_id'): Builder
    {
        $operatorId = self::operatorId();
        if ($operatorId === null) {
            return $query;
        }

        if (str_contains($column, '.')) {
            [$relation, $field] = explode('.', $column, 2);

            return $query->whereHas($relation, fn (Builder $q) => $q->where($field, $operatorId));
        }

        return $query->where($query->qualifyColumn($column), $operatorId);
    }
}
