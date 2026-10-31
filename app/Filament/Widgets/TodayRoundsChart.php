<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TodayRoundsChart extends ChartWidget
{
    protected ?string $heading = 'Rounds — last 24h (hourly)';

    // 60s poll — hourly buckets won't move within a minute anyway.
    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('dashboard.view');
    }

    protected function getData(): array
    {
        // The aggregation (date_trunc + group by) is the expensive part —
        // cache the resulting 24-bucket array for 30s so concurrent
        // dashboard viewers share one DB hit.
        $rows = Cache::remember('admin.rounds-24h-chart', 30, function () {
            $since = Carbon::now()->subHours(24)->startOfHour();

            return GameRound::query()
                ->selectRaw("date_trunc('hour', created_at) as bucket, count(*) as total")
                ->where('created_at', '>=', $since)
                ->whereIn('state', [RoundState::Settled, RoundState::Crashed])
                ->groupBy(DB::raw("date_trunc('hour', created_at)"))
                ->orderBy('bucket')
                ->get()
                ->keyBy(fn ($r) => Carbon::parse($r->bucket)->format('Y-m-d H:00'));
        });

        $labels = [];
        $counts = [];
        for ($i = 23; $i >= 0; $i--) {
            $bucket = Carbon::now()->subHours($i)->startOfHour();
            $key = $bucket->format('Y-m-d H:00');
            $labels[] = $bucket->format('H:00');
            $counts[] = (int) ($rows[$key]->total ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Settled rounds',
                    'data' => $counts,
                    'borderColor' => '#fb923c',
                    'backgroundColor' => 'rgba(251, 146, 60, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
