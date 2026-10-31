<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\GameRound;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\GameRoundResource\Pages;
use App\Support\AccessControl;
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

    /**
     * Rounds are shared by every operator, so the round totals mix all of their
     * stakes. An operator user only gets the rounds their operator bet on, with
     * that operator's own stake and payout selected alongside (see table()).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = AccessControl::scopeToOperator(parent::getEloquentQuery(), 'bets.operator_id')
            ->with('game:id,code');

        $operatorId = AccessControl::operatorId();
        if ($operatorId === null) {
            return $query;
        }

        return $query
            ->addSelect([
                'operator_bet_amount' => Bet::query()
                    ->selectRaw('COALESCE(SUM(bets.amount), 0)')
                    ->whereColumn('bets.round_id', 'game_rounds.id')
                    ->where('bets.operator_id', $operatorId),
                'operator_payout_amount' => Cashout::query()
                    ->selectRaw('COALESCE(SUM(cashouts.payout_amount), 0)')
                    ->join('bets', 'bets.id', '=', 'cashouts.bet_id')
                    ->whereColumn('bets.round_id', 'game_rounds.id')
                    ->where('bets.operator_id', $operatorId),
            ])
            ->withCasts(['operator_bet_amount' => 'decimal:8', 'operator_payout_amount' => 'decimal:8']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        // Operator users get their own share (selected in getEloquentQuery), never
        // the round totals, which include other operators' money.
        $operatorScoped = AccessControl::operatorId() !== null;

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
                TextColumn::make($operatorScoped ? 'operator_bet_amount' : 'total_bet_amount')
                    ->label($operatorScoped ? 'Your stake' : 'Total bet amount')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make($operatorScoped ? 'operator_payout_amount' : 'total_payout_amount')
                    ->label($operatorScoped ? 'Your payout' : 'Total payout amount')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('started_at')->dateTime('H:i:s'),
                TextColumn::make('crashed_at')->dateTime('H:i:s'),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('game')
                    ->relationship('game', 'code', fn (Builder $query) => AccessControl::scopeGamesToOperator($query))
                    ->preload(),
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
