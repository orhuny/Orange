<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameCategoryResource\Pages;

use App\Filament\Resources\GameCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameCategories extends ListRecords
{
    protected static string $resource = GameCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
