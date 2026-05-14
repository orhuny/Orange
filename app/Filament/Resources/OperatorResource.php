<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperatorResource extends Resource
{
    protected static ?string $model = Operator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Operators';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
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
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('warning')
                    ->visible(fn (Operator $record) => $record->status === OperatorStatus::Active)
                    ->action(function (Operator $record) {
                        $record->update(['status' => OperatorStatus::Suspended]);
                        Notification::make()->title('Operator suspended')->warning()->send();
                    })
                    ->requiresConfirmation(),
                Action::make('activate')
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
            ->defaultSort('created_at', 'desc');
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
