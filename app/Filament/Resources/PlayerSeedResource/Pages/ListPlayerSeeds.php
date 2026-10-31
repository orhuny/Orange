<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlayerSeedResource\Pages;

use App\Filament\Resources\PlayerSeedResource;
use Filament\Resources\Pages\ListRecords;

class ListPlayerSeeds extends ListRecords
{
    protected static string $resource = PlayerSeedResource::class;
}
