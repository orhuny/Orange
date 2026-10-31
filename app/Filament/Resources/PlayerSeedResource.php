<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\ProvablyFair\Models\PlayerSeed;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\PlayerSeedResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Players' seed pairs for instant games (platform staff; player_seeds.view). The
 * server seed is shown only once the player revealed it by rotating the pair.
 */
class PlayerSeedResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'player_seeds';

    protected static ?string $model = PlayerSeed::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?string $navigationLabel = 'Player seeds';

    protected static string|\UnitEnum|null $navigationGroup = 'Fairness';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['operator:id,slug', 'player:id,external_player_id']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('operator.slug')->label('Operator'),
                TextColumn::make('player.external_player_id')->label('Player')->searchable(),
                TextColumn::make('server_seed_hash')->label('Server seed hash')->limit(16)->fontFamily('mono')->copyable(),
                TextColumn::make('revealed_server_seed')->label('Server seed')
                    ->state(fn (PlayerSeed $record): ?string => $record->isRevealed() ? $record->server_seed : null)
                    ->limit(16)->fontFamily('mono')->copyable()->placeholder('secret until rotated'),
                TextColumn::make('client_seed')->fontFamily('mono')->limit(16),
                TextColumn::make('nonce')->numeric(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([DateRangeFilter::make('created_at', 'Created')])
            ->defaultSort('id', 'desc')
            ->deferLoading();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlayerSeeds::route('/'),
        ];
    }
}
