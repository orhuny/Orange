<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Game;
use App\Support\AccessControl;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-game KPIs over the dashboard's date range: settled bets, stake, payout, GGR and
 * actual RTP against target. Operator users see only their games and their own bets.
 */
class GameKpiTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Games';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('dashboard.view');
    }

    public function table(Table $table): Table
    {
        $settled = fn (Builder $q): Builder => $this->settledBets($q);

        return $table
            ->query(fn (): Builder => AccessControl::scopeGamesToOperator(Game::query())
                ->when($this->gameId(), fn (Builder $q, int $id) => $q->whereKey($id))
                ->withCount(['bets as settled_bets' => $settled])
                ->withSum(['bets as stake' => $settled], 'amount')
                ->withSum(['bets as payout' => $settled], 'payout_amount'))
            ->columns([
                TextColumn::make('name')->label('Game'),
                TextColumn::make('settled_bets')->label('Bets')->numeric(),
                TextColumn::make('stake')->numeric(decimalPlaces: 2)->placeholder('0.00'),
                TextColumn::make('payout')->numeric(decimalPlaces: 2)->placeholder('0.00'),
                TextColumn::make('ggr')->label('GGR')
                    ->state(fn (Game $record): string => bcsub((string) ($record->getAttribute('stake') ?? '0'), (string) ($record->getAttribute('payout') ?? '0'), 2))
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (string $state): string => bccomp($state, '0', 2) >= 0 ? 'success' : 'danger'),
                TextColumn::make('rtp')->label('RTP')
                    ->state(fn (Game $record): ?string => bccomp((string) ($record->getAttribute('stake') ?? '0'), '0', 8) > 0
                        ? bcmul(bcdiv((string) ($record->getAttribute('payout') ?? '0'), (string) $record->getAttribute('stake'), 6), '100', 2).'%'
                        : null)
                    ->placeholder('—'),
                TextColumn::make('rtp_target')->label('Target')->formatStateUsing(fn ($state): string => number_format((float) $state * 100, 2).'%'),
            ])
            ->paginated(false);
    }

    /**
     * Bets that count for KPIs: settled (won/lost) in the date range, the user's operator only.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function settledBets(Builder $query): Builder
    {
        $from = $this->pageFilters['from'] ?? now()->subDays(6)->toDateString();
        $until = $this->pageFilters['until'] ?? now()->toDateString();

        return AccessControl::scopeToOperator($query)
            ->whereIn('status', [BetStatus::Won->value, BetStatus::Lost->value])
            ->whereDate('settled_at', '>=', (string) $from)
            ->whereDate('settled_at', '<=', (string) $until);
    }

    private function gameId(): ?int
    {
        $id = $this->pageFilters['game'] ?? null;

        return is_numeric($id) ? (int) $id : null;
    }
}
