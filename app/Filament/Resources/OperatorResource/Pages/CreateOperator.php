<?php

declare(strict_types=1);

namespace App\Filament\Resources\OperatorResource\Pages;

use App\Filament\Resources\OperatorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['secret_key'] ??= Str::random(64);

        return $data;
    }
}
