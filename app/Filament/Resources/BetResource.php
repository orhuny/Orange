<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Exports\BetExporter;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\BetResource\Pages;
use App\Support\AccessControl;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BetResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'bets';

    protected static ?string $model = Bet::class;

    /**
     * Eager-load every relation the list table touches so the listing is a
     * single SELECT + one IN-query per relation instead of an N+1 storm.
     * The `net` column uses a state closure that reads $record->cashout,
     * which Filament cannot auto-detect, so we declare it explicitly here.
     */
    public static function getEloquentQuery(): Builder
    {
        return AccessControl::scopeToOperator(parent::getEloquentQuery())
            ->with(['game:id,code', 'round:id,round_uuid', 'operator:id,slug', 'player:id,external_player_id', 'cashout']);
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('game.code')->label('Game')->badge(),
                TextColumn::make('round.round_uuid')->limit(8)->fontFamily('mono')->label('Round')->placeholder('—'),
                TextColumn::make('operator.slug')->searchable(),
                TextColumn::make('player.external_player_id')->label('Player')->searchable(),
                TextColumn::make('amount')->numeric(decimalPlaces: 2),
                TextColumn::make('currency'),
                TextColumn::make('auto_cashout_at')->numeric(decimalPlaces: 2)->suffix('x')->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        BetStatus::Placed => 'info',
                        BetStatus::Won => 'success',
                        BetStatus::Lost => 'danger',
                        BetStatus::Refunded => 'warning',
                        BetStatus::RolledBack => 'gray',
                        BetStatus::Pending => 'warning',
                        BetStatus::Rejected => 'gray',
                        default => 'gray',
                    }),
                // Settlement as recorded on the bet (every game); crash bets settled before
                // the multi-game schema fall back to their cashout.
                TextColumn::make('multiplier')
                    ->label('Multiplier')
                    ->state(fn (Bet $record): ?string => $record->multiplier !== null ? (string) $record->multiplier : $record->cashout?->multiplier)
                    ->numeric(decimalPlaces: 2)
                    ->suffix('x')
                    ->placeholder('—'),
                TextColumn::make('payout_amount')
                    ->label('Payout')
                    ->state(fn (Bet $record): ?string => $record->payout_amount !== null ? (string) $record->payout_amount : $record->cashout?->payout_amount)
                    ->numeric(decimalPlaces: 2)
                    ->color('success')
                    ->placeholder('—'),
                // Only resolved bets have an outcome; nothing is shown before (it isn't stored yet either).
                TextColumn::make('outcome')
                    ->state(fn (Bet $record): ?string => self::outcomeSummary($record))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('net')
                    ->label('Net')
                    ->state(function (Bet $record): ?string {
                        $payout = (string) ($record->payout_amount ?? $record->cashout?->payout_amount ?? '0');
                        $stake = (string) $record->amount;
                        if ($record->status === BetStatus::Lost) {
                            return '-'.number_format((float) $stake, 2);
                        }
                        if ($record->status === BetStatus::Won) {
                            return '+'.number_format((float) bcsub($payout, $stake, 8), 2);
                        }

                        return null;
                    })
                    ->color(fn (Bet $record) => match ($record->status) {
                        BetStatus::Won => 'success',
                        BetStatus::Lost => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('placed_at')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),

                SelectFilter::make('game')
                    ->relationship('game', 'code', fn (Builder $query) => AccessControl::scopeGamesToOperator($query))
                    ->preload(),

                SelectFilter::make('operator')
                    ->relationship('operator', 'slug')
                    ->visible(fn (): bool => AccessControl::operatorId() === null)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('player')
                    ->relationship('player', 'external_player_id', fn (Builder $query) => AccessControl::scopeToOperator($query))
                    ->searchable(),     // not preloaded — player tables can be huge

                DateRangeFilter::make('placed_at', 'Placed'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->authorize(fn (): bool => (bool) auth()->user()?->can('bets.export'))
                    ->exporter(BetExporter::class)
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->persistFiltersInSession()
            ->persistSortInSession();
    }

    /** A one-line view of a resolved bet's outcome, e.g. "roll 44.30 · under 50.00". */
    private static function outcomeSummary(Bet $record): ?string
    {
        $outcome = $record->getAttribute('outcome');
        if (! is_array($outcome) || ! in_array($record->getAttribute('status'), [BetStatus::Won, BetStatus::Lost], true)) {
            return null;
        }

        if (isset($outcome['roll'])) {
            return "roll {$outcome['roll']} · {$outcome['direction']} {$outcome['target']}";
        }

        return collect($outcome)->filter(fn (mixed $v): bool => is_scalar($v))->map(fn (mixed $v, string $k): string => "{$k} {$v}")->implode(' · ');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBets::route('/'),
        ];
    }
}
