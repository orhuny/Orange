<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Models\GameCategory;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Resources\GameCategoryResource\Pages;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Lobby categories and their order (drag to reorder). Platform staff only. */
class GameCategoryResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'lobby';

    protected static ?string $model = GameCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Lobby categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(64),
            TextInput::make('slug')->required()->maxLength(64)->alphaDash()->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Shown in the lobby')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('slug')->fontFamily('mono'),
                TextColumn::make('games_count')->counts('games')->label('Games'),
                IconColumn::make('is_active')->boolean()->label('Shown'),
            ])
            ->recordActions([EditAction::make()])
            ->reorderable('sort_order', fn (): bool => (bool) auth()->user()?->can('lobby.manage'))
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGameCategories::route('/'),
            'create' => Pages\CreateGameCategory::route('/create'),
            'edit' => Pages\EditGameCategory::route('/{record}/edit'),
        ];
    }
}
