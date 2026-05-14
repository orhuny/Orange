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

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?int $navigationSort = 70;

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
                    ->color(fn (WebhookStatus $s) => match ($s) {
                        WebhookStatus::Pending => 'gray',
                        WebhookStatus::Delivered => 'success',
                        WebhookStatus::Failed => 'warning',
                        WebhookStatus::DeadLetter => 'danger',
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
            ->defaultSort('id', 'desc');
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
