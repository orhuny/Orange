<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameRoundResource\Pages;

use App\Filament\Resources\GameRoundResource;
use Filament\Resources\Pages\ListRecords;

class ListGameRounds extends ListRecords
{
    protected static string $resource = GameRoundResource::class;
}
