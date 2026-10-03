<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Filament\Exports\BetExporter;
use App\Filament\Resources\BetResource\Pages;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BetResource extends Resource
{
    protected static ?string $model = Bet::class;

    /**
     * Eager-load every relation the list table touches so the listing is a
     * single SELECT + one IN-query per relation instead of an N+1 storm.
     * The `net` column uses a state closure that reads $record->cashout,
     * which Filament cannot auto-detect, so we declare it explicitly here.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['round:id,round_uuid', 'operator:id,slug', 'player:id,external_player_id', 'cashout']);
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
                TextColumn::make('round.round_uuid')->limit(8)->fontFamily('mono')->label('Round'),
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
                        default => 'gray',
                    }),
                TextColumn::make('cashout.multiplier')
                    ->label('Cashed @')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('x')
                    ->placeholder('—'),
                TextColumn::make('cashout.payout_amount')
                    ->label('Payout')
                    ->numeric(decimalPlaces: 2)
                    ->color('success')
                    ->placeholder('—'),
                TextColumn::make('net')
                    ->label('Net')
                    ->state(function (Bet $record): ?string {
                        $payout = (string) ($record->cashout?->payout_amount ?? '0');
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

                SelectFilter::make('operator')
                    ->relationship('operator', 'slug')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('player')
                    ->relationship('player', 'external_player_id')
                    ->searchable(),     // not preloaded — player tables can be huge

                Filter::make('placed_at')
                    ->label('Placed at')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from']  ?? null, fn ($q, $d) => $q->whereDate('placed_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('placed_at', '<=', $d));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($from  = $data['from']  ?? null) $indicators[] = "From {$from}";
                        if ($until = $data['until'] ?? null) $indicators[] = "Until {$until}";
                        return $indicators;
                    }),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(BetExporter::class)
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->persistFiltersInSession()
            ->persistSortInSession();
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
