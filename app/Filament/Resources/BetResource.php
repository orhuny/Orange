<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use App\Filament\Resources\BetResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BetResource extends Resource
{
    protected static ?string $model = Bet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 40;

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
                TextColumn::make('placed_at')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
            ])
            ->defaultSort('id', 'desc');
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
