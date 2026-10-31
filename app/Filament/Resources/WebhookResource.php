<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Domain\Wallet\Enums\WebhookStatus;
use App\Domain\Wallet\Jobs\DeliverWebhookJob;
use App\Domain\Wallet\Models\Webhook;
use App\Filament\Resources\WebhookResource\Pages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        // Cache for 30s — badge runs on every Livewire render (every page
        // nav, every filter change, every poll). Stale-by-30s is harmless;
        // the user clicks through for live data.
        $failing = Cache::remember(
            'admin.nav.webhooks-failing',
            30,
            fn (): int => static::getModel()::query()
                ->whereIn('status', [WebhookStatus::Failed, WebhookStatus::DeadLetter])
                ->count(),
        );

        return $failing > 0 ? (string) $failing : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('operator:id,slug');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('operator.slug')->searchable(),
                TextColumn::make('event')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        WebhookStatus::Pending => 'gray',
                        WebhookStatus::Delivered => 'success',
                        WebhookStatus::Failed => 'warning',
                        WebhookStatus::DeadLetter => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('attempts'),
                TextColumn::make('next_retry_at')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextColumn::make('delivered_at')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(WebhookStatus::cases())->mapWithKeys(fn ($s) => [$s->value => ucfirst($s->value)])),
            ])
            ->recordActions([
                Action::make('redeliver')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->visible(fn (Webhook $w) => $w->status !== WebhookStatus::Delivered)
                    ->requiresConfirmation()
                    ->action(function (Webhook $record): void {
                        $record->update(['status' => WebhookStatus::Pending, 'next_retry_at' => now()]);
                        DeliverWebhookJob::dispatch($record->id);
                        Notification::make()->title('Webhook re-queued')->success()->send();
                    }),
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
            'index' => Pages\ListWebhooks::route('/'),
        ];
    }
}
