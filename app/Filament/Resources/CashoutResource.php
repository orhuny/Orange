<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Game\Models\Cashout;
use App\Filament\Concerns\AuthorizesWithPermissions;
use App\Filament\Filters\DateRangeFilter;
use App\Filament\Resources\CashoutResource\Pages;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CashoutResource extends Resource
{
    use AuthorizesWithPermissions;

    protected static string $permission = 'cashouts';

    protected static ?string $model = Cashout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Game';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('bet_id')->sortable(),
                TextColumn::make('multiplier')->numeric(decimalPlaces: 2)->suffix('x')->sortable(),
                TextColumn::make('payout_amount')->numeric(decimalPlaces: 2),
                IconColumn::make('is_auto')->boolean()->label('Auto'),
                TextColumn::make('cashed_out_at')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_auto')->label('Auto cashout'),
                DateRangeFilter::make('cashed_out_at', 'Cashed out'),
            ])
            ->defaultSort('id', 'desc')
            ->deferLoading()
            ->persistFiltersInSession();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashouts::route('/'),
        ];
    }
}
