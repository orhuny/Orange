<?php

declare(strict_types=1);

namespace App\Filament\Resources\BetResource\Pages;

use App\Filament\Resources\BetResource;
use Filament\Resources\Pages\ListRecords;

class ListBets extends ListRecords
{
    protected static string $resource = BetResource::class;
}
