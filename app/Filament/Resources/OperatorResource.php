<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\OperatorResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class OperatorResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'operators';

    protected static ?string $model = Operator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Operators';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        return (string) Cache::remember(
            'admin.nav.operators-active',
            30,
            fn (): int => static::getModel()::query()
                ->where('status', OperatorStatus::Active)
                ->count(),
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('apiKeys');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Read-only credentials on edit. The secret is masked server-side, so
            // only its first/last 4 characters ever reach the browser.
            Section::make('API credentials')
                ->schema([
                    TextEntry::make('id')
                        ->label('Operator ID')
                        ->fontFamily(FontFamily::Mono)
                        ->copyable(),
                    TextEntry::make('secret_key')
                        ->label('Secret key')
                        ->state(fn (Operator $record): string => static::maskSecret((string) $record->secret_key))
                        ->fontFamily(FontFamily::Mono)
                        ->helperText('Only the first and last 4 characters are shown.'),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->hiddenOn('create'),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255),
            Select::make('status')
                ->options(collect(OperatorStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)]))
                ->required()
                ->default(OperatorStatus::Active->value),
            TextInput::make('callback_base_url')->url()->maxLength(500),
            KeyValue::make('config')->keyLabel('Key')->valueLabel('Value'),
            KeyValue::make('ip_whitelist')->keyLabel('Label')->valueLabel('CIDR'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->copyable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        OperatorStatus::Active => 'success',
                        OperatorStatus::Suspended => 'warning',
                        OperatorStatus::Disabled => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('callback_base_url')->limit(40)->toggleable(),
                TextColumn::make('apiKeys_count')->counts('apiKeys')->label('API keys'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(OperatorStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
                DateRangeFilter::make('created_at', 'Created'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')
                    ->authorize(fn (): bool => (bool) auth()->user()?->can('operators.manage'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('warning')
                    ->visible(fn (Operator $record) => $record->status === OperatorStatus::Active)
                    ->action(function (Operator $record) {
                        $record->update(['status' => OperatorStatus::Suspended]);
                        Notification::make()->title('Operator suspended')->warning()->send();
                    })
                    ->requiresConfirmation(),
                Action::make('activate')
                    ->authorize(fn (): bool => (bool) auth()->user()?->can('operators.manage'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Operator $record) => $record->status !== OperatorStatus::Active)
                    ->action(function (Operator $record) {
                        $record->update(['status' => OperatorStatus::Active]);
                        Notification::make()->title('Operator activated')->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->persistFiltersInSession()
            ->persistSortInSession();
    }

    public static function maskSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return str_repeat('•', 8);
        }

        return substr($secret, 0, 4).str_repeat('•', 12).substr($secret, -4);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOperators::route('/'),
            'create' => Pages\CreateOperator::route('/create'),
            'edit' => Pages\EditOperator::route('/{record}/edit'),
        ];
    }
}
