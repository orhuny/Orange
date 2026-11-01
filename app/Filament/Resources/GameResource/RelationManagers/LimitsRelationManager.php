<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/** A game's own limits per currency (games.manage). With none, it takes any currency, unlimited. */
class LimitsRelationManager extends RelationManager
{
    protected static string $relationship = 'limits';

    protected static ?string $title = 'Limits per currency';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('games.view');
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('games.manage');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('currency')->required()->length(3)
                ->dehydrateStateUsing(fn (?string $state): string => strtoupper((string) $state))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('game_id', $this->getOwnerRecord()->getKey())),
            TextInput::make('min_bet')->label('Min bet')->required()->numeric()->minValue(0),
            TextInput::make('max_bet')->label('Max bet')->required()->numeric()->gte('min_bet'),
            // Above the max bet, or a win could pay less than its stake.
            TextInput::make('max_win')->label('Max win')->required()->numeric()->gt('max_bet'),
        ]);
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => (bool) auth()->user()?->can('games.manage');

        return $table
            ->recordTitleAttribute('currency')
            ->columns([
                TextColumn::make('currency')->badge(),
                TextColumn::make('min_bet')->label('Min bet')->numeric(decimalPlaces: 2),
                TextColumn::make('max_bet')->label('Max bet')->numeric(decimalPlaces: 2),
                TextColumn::make('max_win')->label('Max win')->numeric(decimalPlaces: 2),
            ])
            ->headerActions([
                CreateAction::make()->authorize($canManage)->modalDescription(fn (): ?string => $this->firstRowWarning()),
            ])
            ->recordActions([
                EditAction::make()->authorize($canManage),
                DeleteAction::make()->authorize($canManage),
            ])
            ->defaultSort('currency');
    }

    /** Adding the first row cuts off every other currency: say which ones are in use. */
    private function firstRowWarning(): ?string
    {
        /** @var Game $game */
        $game = $this->getOwnerRecord();
        if (GameLimit::query()->where('game_id', $game->id)->exists()) {
            return null;
        }

        $recent = Bet::query()->where('game_id', $game->id)->where('placed_at', '>=', now()->subDays(30))
            ->distinct()->orderBy('currency')->pluck('currency');

        return 'Once this game has any limits, bets in currencies without a row are refused. '
            .($recent->isEmpty() ? 'No bets in the last 30 days.' : 'Currencies bet in the last 30 days: '.$recent->implode(', ').'.');
    }
}
