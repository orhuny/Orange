<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps Filament's resource abilities onto App\Support\AccessControl permissions:
 * listing/viewing needs "{key}.view", anything that changes data needs
 * "{key}.manage". The using resource declares `protected static string $permission`.
 *
 * Custom table/header actions aren't covered by Filament's resource checks —
 * those call ->authorize(...) with their own permission.
 */
trait AuthorizesWithPermissions
{
    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $ability = in_array($action, ['viewAny', 'view'], true) ? 'view' : 'manage';

        return auth()->user()?->can(static::$permission.'.'.$ability)
            ? Response::allow()
            : Response::deny();
    }
}
