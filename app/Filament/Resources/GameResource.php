<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Models\Game;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\GameResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GameResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'games';

    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('rounds');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true),
            TextInput::make('name')->required(),
            TextInput::make('version')->numeric()->default(1),
            // rtp_target and house_edge are wired bi-directionally — editing
            // one auto-fills the other so they always sum to 1.0. The model's
            // saving event still enforces the invariant as a final safety net.
            TextInput::make('rtp_target')
                ->numeric()
                ->step('0.0001')
                ->minValue(0.0001)->maxValue(0.9999)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, $state): void {
                    if (is_numeric($state)) {
                        $set('house_edge', round(1.0 - (float) $state, 4));
                    }
                }),
            TextInput::make('house_edge')
                ->numeric()
                ->step('0.0001')
                ->minValue(0.0001)->maxValue(0.9999)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, $state): void {
                    if (is_numeric($state)) {
                        $set('rtp_target', round(1.0 - (float) $state, 4));
                    }
                }),
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
            ->defaultSort('code')
            ->deferLoading();
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
