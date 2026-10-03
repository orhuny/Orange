<?php

declare(strict_types=1);

namespace App\Filament\Resources\BetResource\Pages;

use App\Filament\Resources\BetResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListBets extends ListRecords
{
    protected static string $resource = BetResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
