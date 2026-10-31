<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Operator\Models\Player;
use App\Filament\Resources\PlayerResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('operator:id,slug');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('external_player_id')->searchable()->copyable(),
                TextColumn::make('operator.slug')->searchable(),
                TextColumn::make('currency'),
                TextColumn::make('country')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('risk_score')->numeric()->placeholder('0'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->persistFiltersInSession();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayers::route('/'),
        ];
    }
}
