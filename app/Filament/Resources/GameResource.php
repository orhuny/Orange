<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Models\Game;
use App\Filament\Resources\GameResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GameResource extends Resource
{
    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            TextInput::make('version')->numeric()->default(1),
            TextInput::make('rtp_target')->numeric()->step('0.0001'),
            TextInput::make('house_edge')->numeric()->step('0.0001'),
            Select::make('status')->options(['active' => 'Active', 'disabled' => 'Disabled'])->required(),
            KeyValue::make('config'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('version'),
                TextColumn::make('rtp_target')->label('RTP target')->numeric(decimalPlaces: 4),
                TextColumn::make('house_edge')->numeric(decimalPlaces: 4),
                TextColumn::make('status')->badge(),
                TextColumn::make('rounds_count')->counts('rounds')->label('Rounds'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
