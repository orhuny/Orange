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
    protected ?string $heading = 'Rounds';

    protected ?string $description = 'Settled rounds per hour, last 24 hours';

    protected ?string $maxHeight = '260px';

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
        // dashboard viewers share one DB hit. Plain hour => count: the cache
        // never unserializes objects (cache.serializable_classes = false).
        /** @var array<string, int> $rows */
        $rows = Cache::remember('admin.rounds-24h-chart', 30, function (): array {
            $since = Carbon::now()->subHours(24)->startOfHour();
            // Postgres in production; SQLite (tests, local runs) has no date_trunc.
            $hour = DB::connection()->getDriverName() === 'sqlite'
                ? "strftime('%Y-%m-%d %H:00:00', created_at)"
                : "date_trunc('hour', created_at)";

            return GameRound::query()
                ->selectRaw("{$hour} as bucket, count(*) as total")
                ->where('created_at', '>=', $since)
                ->whereIn('state', [RoundState::Settled, RoundState::Crashed])
                ->groupBy(DB::raw($hour))
                ->orderBy('bucket')
                ->get()
                ->mapWithKeys(fn (GameRound $r): array => [
                    Carbon::parse((string) $r->getAttribute('bucket'))->format('Y-m-d H:00') => (int) $r->getAttribute('total'),
                ])
                ->all();
        });

        $labels = [];
        $counts = [];
        for ($i = 23; $i >= 0; $i--) {
            $bucket = Carbon::now()->subHours($i)->startOfHour();
            $key = $bucket->format('Y-m-d H:00');
            $labels[] = $bucket->format('H:00');
            $counts[] = $rows[$key] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Settled rounds',
                    'data' => $counts,
                    // The panel's primary (indigo).
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
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
