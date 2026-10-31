<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameCategoryResource\Pages;

use App\Filament\Resources\GameCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditGameCategory extends EditRecord
{
    protected static string $resource = GameCategoryResource::class;
}
