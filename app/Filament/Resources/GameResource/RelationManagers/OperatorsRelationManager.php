<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameResource\RelationManagers;

use App\Domain\Game\Limits\OperatorLimitsEditor;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\OperatorGame;
use App\Filament\Resources\GameResource;
use App\Support\AccessControl;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use InvalidArgumentException;

/**
 * Which operators a game is enabled for, their play mode and their own (stricter)
 * limits. Platform staff only: operator users never see other operators.
 */
class OperatorsRelationManager extends RelationManager
{
    protected static string $relationship = 'operatorGames';

    protected static ?string $title = 'Operators';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AccessControl::operatorId() === null && (bool) auth()->user()?->can('games.view');
    }

    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('game_access.manage');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('operator_id')->relationship('operator', 'name')->required()->searchable()->preload()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('game_id', $this->getOwnerRecord()->getKey()))
                ->disabledOn('edit'),
            Toggle::make('enabled')->default(true)
                ->helperText('Off: this operator cannot list, launch or bet on the game.'),
            Select::make('settings.play_mode')->label('Play mode')->options([
                'relay' => 'Relay (the operator places bets through /v1)',
                'direct' => 'Direct (launch / lobby; the page places bets)',
            ])->default('relay'),
            TextInput::make('sort_order')->numeric()->helperText("Overrides the game's order in this operator's catalogue."),
        ]);
    }

    public function table(Table $table): Table
    {
        $canManage = fn (): bool => (bool) auth()->user()?->can('game_access.manage');

        return $table
            ->recordTitleAttribute('operator.name')
            ->columns([
                TextColumn::make('operator.name')->label('Operator')->searchable(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('settings.play_mode')->label('Play mode')->badge()->placeholder('relay'),
                TextColumn::make('limits.currency')->label('Own limits')->badge()->placeholder('—'),
                TextColumn::make('sort_order')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()->label('Add operator')->authorize($canManage)])
            ->recordActions([
                EditAction::make()->authorize($canManage),
                Action::make('limits')
                    ->label('Limits')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->authorize($canManage)
                    ->modalDescription('Stricter limits for this operator only; they can never loosen the game\'s own limits.')
                    ->fillForm(fn (OperatorGame $record): array => ['limits' => $record->limits()->orderBy('currency')->get()
                        ->map(fn ($l): array => ['currency' => $l->currency, 'min_bet' => $l->min_bet, 'max_bet' => $l->max_bet, 'max_win' => $l->max_win])
                        ->values()->all()])
                    ->schema([GameResource::limitsRepeater()])
                    ->action(function (OperatorGame $record, array $data, Action $action): void {
                        /** @var Game $game */
                        $game = $this->getOwnerRecord();
                        try {
                            app(OperatorLimitsEditor::class)->replace((string) $record->operator_id, $game, array_values((array) ($data['limits'] ?? [])));
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title('Limits not saved')->body($e->getMessage())->danger()->send();
                            $action->halt();
                        }
                        Notification::make()->title('Limits saved')->success()->send();
                    }),
                DeleteAction::make()->authorize($canManage),
            ]);
    }
}
