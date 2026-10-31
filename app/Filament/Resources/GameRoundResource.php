<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\GameRoundResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class GameRoundResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'rounds';

    protected static ?string $model = GameRound::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Live rounds';

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 20;

    public static function getNavigationBadge(): ?string
    {
        // Short 5s TTL — live rounds count is the most time-sensitive of
        // the three nav badges (it ticks every round, ~10s cycle), so we
        // can't be as lazy as webhook/operator badges.
        return (string) Cache::remember(
            'admin.nav.live-rounds',
            5,
            fn (): int => static::getModel()::query()
                ->whereIn('state', [RoundState::Betting, RoundState::Running])
                ->count(),
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('game:id,code');
    }

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
                    ->color(fn ($state) => match ($state) {
                        RoundState::Waiting => 'gray',
                        RoundState::Betting => 'info',
                        RoundState::Running => 'success',
                        RoundState::Crashed => 'danger',
                        RoundState::Settled => 'gray',
                        default => 'gray',
                    }),
                // Never the live round's crash point: only shown once it has crashed.
                TextColumn::make('crash_point')
                    ->state(fn (GameRound $record): ?float => $record->publicCrashPoint())
                    ->numeric(decimalPlaces: 2)
                    ->suffix('x')
                    ->placeholder('—'),
                TextColumn::make('total_bet_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('total_payout_amount')->numeric(decimalPlaces: 2),
                TextColumn::make('started_at')->dateTime('H:i:s'),
                TextColumn::make('crashed_at')->dateTime('H:i:s'),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->options(collect(RoundState::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
                DateRangeFilter::make('created_at', 'Round'),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->persistFiltersInSession()
            ->poll('8s');
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
