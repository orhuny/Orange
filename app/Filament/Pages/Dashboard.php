<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Game\Models\Game;
use App\Support\AccessControl;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/** The dashboard, filterable by game and (for the per-game report) by date range. */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('game')
                ->label('Game')
                ->placeholder('All games')
                // Operator users can only pick games their operator offers.
                ->options(fn (): array => AccessControl::scopeGamesToOperator(Game::query())->orderBy('name')->pluck('name', 'id')->all()),
            DatePicker::make('from')->label('From')->native(false)->maxDate(now())->default(now()->subDays(6)->toDateString()),
            DatePicker::make('until')->label('Until')->native(false)->maxDate(now())->default(now()->toDateString()),
        ]);
    }
}
