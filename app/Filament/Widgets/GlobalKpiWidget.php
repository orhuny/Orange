<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Cashout;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class GlobalKpiWidget extends StatsOverviewWidget
{
    // 30s poll — internal admin tool, 30s freshness is plenty. Combined
    // with the 10s Cache::remember below, multiple admins viewing the
    // dashboard share the same query result.
    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        // The 6 underlying queries are deterministic for any given second
        // and cheap to share across viewers. A 10s cache cuts DB load by
        // an order of magnitude when multiple admins watch the dashboard.
        [
            'todayRounds' => $todayRounds,
            'totalBet' => $totalBet,
            'totalPayout' => $totalPayout,
            'activeRounds' => $activeRounds,
            'activeOperators' => $activeOperators,
        ] = Cache::remember('admin.kpi-widget', 10, function (): array {
            $today = Carbon::today();

            return [
                'todayRounds' => GameRound::query()
                    ->where('created_at', '>=', $today)
                    ->whereIn('state', [RoundState::Settled, RoundState::Crashed])
                    ->count(),
                'totalBet' => (string) Bet::query()
                    ->where('placed_at', '>=', $today)
                    ->sum('amount'),
                'totalPayout' => (string) Cashout::query()
                    ->where('cashed_out_at', '>=', $today)
                    ->sum('payout_amount'),
                'activeRounds' => GameRound::query()
                    ->whereIn('state', [RoundState::Betting, RoundState::Running])
                    ->count(),
                'activeOperators' => Operator::query()
                    ->where('status', OperatorStatus::Active)
                    ->count(),
            ];
        });

        $ggr = bcsub($totalBet ?: '0', $totalPayout ?: '0', 2);
        $rtp = bccomp($totalBet ?: '0', '0', 2) > 0
            ? round(((float) $totalPayout / (float) $totalBet) * 100, 2)
            : 0.0;

        return [
            Stat::make('Active operators', $activeOperators)
                ->icon('heroicon-o-building-office-2')
                ->color('emerald'),

            Stat::make('Live rounds', $activeRounds)
                ->description('betting + running right now')
                ->icon('heroicon-o-rocket-launch')
                ->color($activeRounds > 0 ? 'success' : 'gray'),

            Stat::make('Rounds today', number_format($todayRounds))
                ->description('settled / crashed since midnight')
                ->icon('heroicon-o-chart-bar'),

            Stat::make('Total bet today', $this->fmt($totalBet))
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('Total payout today', $this->fmt($totalPayout))
                ->icon('heroicon-o-banknotes')
                ->color('amber'),

            Stat::make('GGR today', $this->fmt($ggr))
                ->description($rtp.'% RTP')
                ->descriptionIcon($rtp >= 95 ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up')
                ->icon('heroicon-o-presentation-chart-line')
                ->color(bccomp($ggr, '0', 2) >= 0 ? 'success' : 'danger'),
        ];
    }

    private function fmt(string $n): string
    {
        return number_format((float) $n, 2);
    }
}
