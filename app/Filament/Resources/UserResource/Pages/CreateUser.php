<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Support\AccessControl;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // An operator admin can only ever create users for their own operator.
        if (($operatorId = AccessControl::operatorId()) !== null) {
            $data['operator_id'] = $operatorId;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Roles are synced through the relationship, bypassing spatie's cache flush.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
