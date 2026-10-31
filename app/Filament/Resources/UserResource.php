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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
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
 * Admin-panel accounts. Guards against lock-out and privilege escalation:
 * nobody edits their own roles/active flag or deletes themselves, and only a
 * Super Admin can touch a Super Admin or hand out that role.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 80;

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        $actor = auth()->user();

        if (! $actor?->can('users.manage')) {
            return Response::deny();
        }

        if ($record instanceof User && $action !== 'view') {
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
            Toggle::make('is_active')
                ->label('Active')
                ->helperText('Inactive users cannot sign in.')
                ->default(true)
                ->disabled($isSelf),
            CheckboxList::make('roles')
                ->relationship(
                    'roles',
                    'name',
                    modifyQueryUsing: fn (Builder $query) => AccessControl::isSuperAdmin(auth()->user())
                        ? $query->orderBy('name')
                        : $query->where('name', '!=', AccessControl::SUPER_ADMIN)->orderBy('name'),
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
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->preload(),
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
