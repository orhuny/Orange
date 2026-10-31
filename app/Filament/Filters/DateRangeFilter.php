<?php

declare(strict_types=1);

namespace App\Filament\Filters;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * From / until date filter shared by every admin report table. Both ends are
 * inclusive whole days on the given timestamp column.
 */
final class DateRangeFilter
{
    public static function make(string $column, string $label = 'Date'): Filter
    {
        return Filter::make($column)
            ->label($label)
            ->schema([
                DatePicker::make('from')->label("{$label} from")->native(false)->maxDate(now()),
                DatePicker::make('until')->label("{$label} until")->native(false)->maxDate(now()),
            ])
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $q, string $d) => $q->whereDate($column, '>=', $d))
                ->when($data['until'] ?? null, fn (Builder $q, string $d) => $q->whereDate($column, '<=', $d)))
            ->indicateUsing(function (array $data) use ($label): array {
                $indicators = [];
                if ($from = $data['from'] ?? null) {
                    $indicators[] = "{$label} from {$from}";
                }
                if ($until = $data['until'] ?? null) {
                    $indicators[] = "{$label} until {$until}";
                }

                return $indicators;
            });
    }
}
