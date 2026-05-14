<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use App\Filament\Resources\GameRoundResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GameRoundResource extends Resource
{
    protected static ?string $model = GameRound::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Live rounds';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('round_uuid')->limit(8)->copyable()->fontFamily('mono'),
                TextColumn::make('game.code'),
                TextColumn::make('state')
                    ->badge()
                    ->color(fn (RoundState $s) => match ($s) {
                        RoundState::Waiting => 'gray',
                        RoundState::Betting => 'info',
                        RoundState::Running => 'success',
                        RoundState::Crashed => 'danger',
                        RoundState::Settled => 'gray',
                    }),
                TextColumn::make('crash_point')->numeric(decimalPlaces: 2)->suffix('x'),
                TextColumn::make('total_bet_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('total_payout_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('started_at')->dateTime('H:i:s'),
                TextColumn::make('crashed_at')->dateTime('H:i:s'),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(collect(RoundState::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
            ])
            ->defaultSort('id', 'desc')
            ->poll('5s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGameRounds::route('/'),
        ];
    }
}
