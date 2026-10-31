<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\AccessControl;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles and the permissions they grant (catalog in App\Support\AccessControl).
 * The built-in Super Admin role is locked: it implicitly has everything.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 81;

    public static function getAuthorizationResponse(string $action, ?Model $record = null): Response
    {
        if (! auth()->user()?->can('roles.manage')) {
            return Response::deny();
        }

        if ($record instanceof Role && $record->name === AccessControl::SUPER_ADMIN && ! in_array($action, ['view', 'viewAny'], true)) {
            return Response::deny('The Super Admin role always has every permission.');
        }

        return Response::allow();
    }

    public static function form(Schema $schema): Schema
    {
        $labels = [];
        foreach (AccessControl::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                $labels[$name] = "{$group} — {$label}";
            }
        }

        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            Hidden::make('guard_name')->default('web'),
            // Fixed after creation: users are matched to roles by scope.
            Select::make('scope')
                ->options([
                    AccessControl::SCOPE_PLATFORM => 'Platform — our staff, all operators',
                    AccessControl::SCOPE_OPERATOR => 'Operator — an operator\'s staff, their own data only',
                ])
                ->default(AccessControl::SCOPE_PLATFORM)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('permissions', []))
                ->disabledOn('edit'),
            CheckboxList::make('permissions')
                ->relationship(
                    'permissions',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->whereIn('name', AccessControl::permissionsForScope((string) $get('scope')))
                        ->orderBy('name'),
                )
                ->getOptionLabelFromRecordUsing(fn (Permission $record): string => $labels[$record->name] ?? $record->name)
                ->bulkToggleable()
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->badge()->searchable()->sortable(),
                TextColumn::make('scope')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === AccessControl::SCOPE_OPERATOR ? 'info' : 'gray'),
                TextColumn::make('users_count')->counts('users')->label('Users'),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions')
                    ->formatStateUsing(fn (int $state, Role $record): string => $record->name === AccessControl::SUPER_ADMIN ? 'All' : (string) $state),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
