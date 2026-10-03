<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Models\Bet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class BetExporter extends Exporter
{
    protected static ?string $model = Bet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('round.round_uuid')->label('Round UUID'),
            ExportColumn::make('operator.slug')->label('Operator'),
            ExportColumn::make('player.external_player_id')->label('Player'),
            ExportColumn::make('amount')->label('Stake'),
            ExportColumn::make('currency')->label('Currency'),
            ExportColumn::make('auto_cashout_at')->label('Auto cashout x'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('cashout.multiplier')->label('Cashed out at x'),
            ExportColumn::make('cashout.payout_amount')->label('Payout amount'),
            ExportColumn::make('cashout.is_auto')
                ->label('Auto cashout?')
                ->formatStateUsing(fn ($state) => $state === null ? '' : ($state ? 'yes' : 'no')),
            ExportColumn::make('net')
                ->label('Net (player P&L)')
                ->state(function (Bet $record): ?string {
                    $payout = (string) ($record->cashout?->payout_amount ?? '0');
                    $stake = (string) $record->amount;
                    if ($record->status === BetStatus::Lost) {
                        return '-'.number_format((float) $stake, 2, '.', '');
                    }
                    if ($record->status === BetStatus::Won) {
                        return '+'.number_format((float) bcsub($payout, $stake, 8), 2, '.', '');
                    }
                    return null;
                }),
            ExportColumn::make('external_tx_id')->label('Wallet tx id'),
            ExportColumn::make('idempotency_key')->label('Idempotency key'),
            ExportColumn::make('placed_at')->label('Placed at'),
            ExportColumn::make('created_at')->label('Created at'),
        ];
    }

    /**
     * Force every export query to also pull the relations we read in the
     * column closures so we don't bloat the worker with one query per row.
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'round:id,round_uuid',
            'operator:id,slug',
            'player:id,external_player_id',
            'cashout',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $count = number_format($export->successful_rows);
        $body = "Your bet export has finished — {$count} rows are ready to download.";

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failed).' row(s) failed to export.';
        }

        return $body;
    }
}
