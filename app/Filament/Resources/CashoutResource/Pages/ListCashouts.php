<?php

declare(strict_types=1);

namespace App\Filament\Resources\CashoutResource\Pages;

use App\Filament\Resources\CashoutResource;
use Filament\Resources\Pages\ListRecords;

class ListCashouts extends ListRecords
{
    protected static string $resource = CashoutResource::class;
}
