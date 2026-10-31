<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\AccessControl;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-panel accounts, for platform staff and for operators' own staff.
 *
 * - Operator admins only see and create users of their own operator, with
 *   operator-scoped roles (the operator is forced on create).
 * - Platform admins pick the operator (or none = platform staff); the role
 *   list follows that choice.
 * - Nobody edits their own roles/active flag or deletes themselves, and only a
 *   Super Admin can touch a Super Admin or hand out that role.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 80;

    public static function getEloquentQuery(): Builder
    {
        return AccessControl::scopeToOperator(parent::getEloquentQuery())->with('operator:id,name');
    }

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $actor = auth()->user();

        if (! $actor?->can('users.manage')) {
            return Response::deny();
        }

        if ($record instanceof User && $action !== 'view') {
            if ($actor->isOperatorUser() && $record->operator_id !== $actor->operator_id) {
                return Response::deny();
            }
            if (AccessControl::isSuperAdmin($record) && ! AccessControl::isSuperAdmin($actor)) {
                return Response::deny('Only a Super Admin can change a Super Admin.');
            }
            if ($action === 'delete' && $record->is($actor)) {
                return Response::deny('You cannot delete your own account.');
            }
        }

        return Response::allow();
    }

    public static function form(Schema $schema): Schema
    {
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());
        $actorIsPlatform = fn (): bool => AccessControl::operatorId() === null;

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true)->maxLength(255),
            TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(10)
                ->maxLength(255)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
            Select::make('operator_id')
                ->label('Operator')
                ->relationship('operator', 'name')
                ->searchable()
                ->preload()
                ->placeholder('None — platform staff')
                ->helperText('Users of an operator only ever see that operator\'s data.')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('roles', []))
                ->visible($actorIsPlatform)
                ->disabled($isSelf),
            Toggle::make('is_active')
                ->label('Active')
                ->helperText('Inactive users cannot sign in.')
                ->default(true)
                ->disabled($isSelf),
            CheckboxList::make('roles')
                ->relationship(
                    'roles',
                    'name',
                    modifyQueryUsing: function (Builder $query, Get $get): Builder {
                        $forOperator = AccessControl::operatorId() !== null || filled($get('operator_id'));
                        $query->where('scope', $forOperator ? AccessControl::SCOPE_OPERATOR : AccessControl::SCOPE_PLATFORM);
                        if (! AccessControl::isSuperAdmin(auth()->user())) {
                            $query->where('name', '!=', AccessControl::SUPER_ADMIN);
                        }

                        return $query->orderBy('name');
                    },
                )
                ->helperText(fn (?User $record): string => $isSelf($record)
                    ? 'You cannot change your own roles.'
                    : 'A user needs at least one role to sign in.')
                ->disabled($isSelf)
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $actorIsPlatform = fn (): bool => AccessControl::operatorId() === null;

        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('operator.name')
                    ->label('Operator')
                    ->placeholder('Platform')
                    ->visible($actorIsPlatform),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('operator')
                    ->relationship('operator', 'name')
                    ->preload()
                    ->visible($actorIsPlatform),
                SelectFilter::make('roles')
                    ->relationship(
                        'roles',
                        'name',
                        fn (Builder $query) => AccessControl::operatorId() === null
                            ? $query
                            : $query->where('scope', AccessControl::SCOPE_OPERATOR),
                    )
                    ->preload(),
                TernaryFilter::make('is_active')->label('Active'),
                DateRangeFilter::make('created_at', 'Created'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
