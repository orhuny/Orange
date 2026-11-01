<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Game\Models\Bet;
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
            // Money is never added up across currencies: the figures are for one currency.
            Select::make('currency')
                ->label('Currency')
                ->options(fn (): array => self::currencies())
                ->default(fn (): ?string => array_key_first(self::currencies()))
                ->selectablePlaceholder(false),
            DatePicker::make('from')->label('From')->native(false)->maxDate(now())->default(now()->subDays(6)->toDateString()),
            DatePicker::make('until')->label('Until')->native(false)->maxDate(now())->default(now()->toDateString()),
        ]);
    }

    /**
     * Currencies bet in over the last 30 days (the user's operator only), most bet first.
     *
     * @return array<string, string>
     */
    public static function currencies(): array
    {
        $currencies = AccessControl::scopeToOperator(Bet::query())
            ->where('placed_at', '>=', now()->subDays(30))
            ->selectRaw('currency, count(*) as bets')
            ->groupBy('currency')
            ->orderByDesc('bets')
            ->pluck('currency')
            ->all();

        return array_combine($currencies, $currencies) ?: ['EUR' => 'EUR'];
    }
}
