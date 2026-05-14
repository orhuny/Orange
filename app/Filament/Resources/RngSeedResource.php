<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\ProvablyFair\Models\RngSeed;
use App\Domain\ProvablyFair\Services\SeedRotator;
use App\Filament\Resources\RngSeedResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RngSeedResource extends Resource
{
    protected static ?string $model = RngSeed::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'RNG seeds';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('server_seed_hash')->limit(16)->fontFamily('mono')->copyable(),
                TextColumn::make('used_round_count')->numeric(),
                TextColumn::make('rotated_at')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextColumn::make('revealed_at')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('rotateNow')
                    ->label('Rotate active seed')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(SeedRotator::class)->rotate();
                        Notification::make()->title('Seed rotated')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('reveal')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('danger')
                    ->visible(fn (RngSeed $r) => $r->rotated_at !== null && $r->revealed_at === null)
                    ->requiresConfirmation()
                    ->action(function (RngSeed $record): void {
                        app(SeedRotator::class)->reveal($record);
                        Notification::make()->title('Seed revealed')->success()->send();
                    }),
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
            'index' => Pages\ListRngSeeds::route('/'),
        ];
    }
}
