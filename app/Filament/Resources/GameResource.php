<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Enums\GameAccess;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Limits\OperatorLimitsEditor;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameLimit;
use App\Domain\Game\Models\OperatorGame;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\GameEngines\EngineConfigForm;
use App\Filament\Resources\GameResource\Pages;
use App\Filament\Resources\GameResource\RelationManagers;
use App\Support\AccessControl;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class GameResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'games';

    protected static ?string $model = Game::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 10;

    public const STATUSES = [
        'active' => 'Active',
        'maintenance' => 'Maintenance (listed, not playable)',
        'disabled' => 'Disabled',
        'draft' => 'Draft (hidden)',
    ];

    /** Operator users see only the games their operator may offer (AccessControl::scopeGamesToOperator). */
    public static function getEloquentQuery(): Builder
    {
        return AccessControl::scopeGamesToOperator(parent::getEloquentQuery())
            ->with(['category:id,name', 'limits:id,game_id,currency']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Game')->columns(2)->schema([
                Select::make('type')
                    ->options(fn (): array => array_combine(app(EngineRegistry::class)->types(), app(EngineRegistry::class)->types()))
                    ->required()
                    ->live()
                    // The type decides how existing bets were played; it can't change afterwards.
                    ->disabledOn('edit'),
                TextInput::make('code')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true)
                    ->helperText('Used in API URLs (/v1/games/{code}/…); fixed once created.')
                    ->disabledOn('edit'),
                TextInput::make('name')->required()->maxLength(128),
                TextInput::make('slug')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true),
                Select::make('category_id')->label('Lobby category')->relationship('category', 'name'),
                FileUpload::make('thumbnail')->image()->disk('public')->directory('thumbs')->maxSize(1024),
                Select::make('status')->options(self::STATUSES)->required()->default('draft'),
                Select::make('access')->options([
                    GameAccess::All->value => 'Every operator, unless switched off for one',
                    GameAccess::Listed->value => 'Only operators it is enabled for',
                ])->required()->default(GameAccess::Listed->value),
                TextInput::make('sort_order')->numeric()->default(0),
                TextInput::make('version')->numeric()->default(1),
            ]),
            Section::make('Odds')->columns(2)->schema([
                // rtp_target and house_edge are wired bi-directionally — editing one
                // auto-fills the other so they always sum to 1.0. The model's saving
                // event still enforces the invariant as a final safety net.
                TextInput::make('rtp_target')->label('RTP target')->numeric()->step('0.0001')->minValue(0.0001)->maxValue(0.9999)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, $state) => is_numeric($state) ? $set('house_edge', round(1.0 - (float) $state, 4)) : null),
                TextInput::make('house_edge')->numeric()->step('0.0001')->minValue(0.0001)->maxValue(0.9999)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, $state) => is_numeric($state) ? $set('rtp_target', round(1.0 - (float) $state, 4)) : null),
            ]),
            Section::make('Engine settings')->schema(self::engineSettings()),
        ]);
    }

    /** One group of fields per registered engine form, shown for games of that type. */
    private static function engineSettings(): array
    {
        $groups = [];
        foreach ((array) config('games.admin_forms', []) as $type => $form) {
            if (is_string($form) && is_subclass_of($form, EngineConfigForm::class)) {
                $groups[] = Group::make($form::components())->visible(fn (Get $get): bool => $get('type') === $type);
            }
        }

        return $groups;
    }

    /**
     * Normalises the engine settings before a save, turning an engine's refusal into a
     * form error instead of a failed save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function validateEngineConfig(array $data, ?Game $record = null): array
    {
        $type = (string) ($data['type'] ?? $record?->getAttribute('type') ?? '');
        $engines = app(EngineRegistry::class);
        if (! $engines->has($type)) {
            return $data;
        }

        $game = ($record ?? new Game)->replicate()->forceFill(array_intersect_key($data, array_flip(['rtp_target', 'house_edge'])));
        // An engine form without fields (crash) sends no config: keep what the game has.
        $config = array_key_exists('config', $data) ? (array) $data['config'] : (array) $record?->getAttribute('config');
        try {
            $data['config'] = $engines->for($type)->validateConfig($game, $config);
        } catch (InvalidGameConfig $e) {
            throw ValidationException::withMessages(['data.config' => $e->getMessage()]);
        }

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('category.name')->label('Category')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'maintenance' => 'warning',
                    'disabled' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('access')->badge()->color('gray'),
                TextColumn::make('limits.currency')->label('Currencies')->badge()->placeholder('any'),
                TextColumn::make('rtp_target')->label('RTP')->numeric(decimalPlaces: 4),
                TextColumn::make('sort_order')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(fn (): array => array_combine(app(EngineRegistry::class)->types(), app(EngineRegistry::class)->types())),
                SelectFilter::make('status')->options(self::STATUSES),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::ownLimitsAction(),
            ])
            ->defaultSort('sort_order')
            ->deferLoading();
    }

    /** Operator users: tighten their own operator's limits for a game (game_limits.tighten). */
    private static function ownLimitsAction(): Action
    {
        return Action::make('ownLimits')
            ->label('My limits')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->visible(fn (): bool => AccessControl::operatorId() !== null && (bool) auth()->user()?->can('game_limits.tighten'))
            ->authorize(fn (): bool => AccessControl::operatorId() !== null && (bool) auth()->user()?->can('game_limits.tighten'))
            ->modalDescription(fn (Game $record): string => self::gameLimitsSummary($record).' Your limits can only be stricter.')
            ->fillForm(fn (Game $record): array => ['limits' => self::ownLimits($record)])
            ->schema([self::limitsRepeater()])
            ->action(function (Game $record, array $data, Action $action): void {
                try {
                    app(OperatorLimitsEditor::class)->replace((string) AccessControl::operatorId(), $record, array_values((array) ($data['limits'] ?? [])));
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Limits not saved')->body($e->getMessage())->danger()->send();
                    $action->halt();
                }
                Notification::make()->title('Limits saved')->success()->send();
            });
    }

    public static function limitsRepeater(): Repeater
    {
        return Repeater::make('limits')
            ->label('Limits per currency')
            ->schema([
                Grid::make(4)->schema([
                    TextInput::make('currency')->required()->length(3)->dehydrateStateUsing(fn (?string $state): string => strtoupper((string) $state)),
                    TextInput::make('min_bet')->label('Min bet'),
                    TextInput::make('max_bet')->label('Max bet'),
                    TextInput::make('max_win')->label('Max win'),
                ]),
            ])
            ->defaultItems(0)
            ->addActionLabel('Add currency');
    }

    /** @return list<array<string, string|null>> */
    private static function ownLimits(Game $record): array
    {
        $operatorGame = OperatorGame::query()->where('operator_id', AccessControl::operatorId())->where('game_id', $record->id)->first();

        return $operatorGame === null ? [] : $operatorGame->limits()->orderBy('currency')->get()
            ->map(fn ($l): array => ['currency' => $l->currency, 'min_bet' => $l->min_bet, 'max_bet' => $l->max_bet, 'max_win' => $l->max_win])
            ->values()->all();
    }

    private static function gameLimitsSummary(Game $record): string
    {
        $limits = GameLimit::query()->where('game_id', $record->id)->orderBy('currency')->get();

        return $limits->isEmpty()
            ? 'This game has no limits of its own: any currency, any stake.'
            : 'Game limits: '.$limits->map(fn (GameLimit $l): string => "{$l->currency} {$l->min_bet}–{$l->max_bet}, max win {$l->max_win}")->implode('; ').'.';
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LimitsRelationManager::class,
            RelationManagers\OperatorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGames::route('/'),
            'create' => Pages\CreateGame::route('/create'),
            'view' => Pages\ViewGame::route('/{record}'),
            'edit' => Pages\EditGame::route('/{record}/edit'),
        ];
    }
}
