<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Support\AccessControl;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class GlobalKpiWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    // 30s poll — internal admin tool, 30s freshness is plenty. Combined
    // with the 10s Cache::remember below, multiple admins viewing the
    // dashboard share the same query result.
    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('dashboard.view');
    }

    protected function getStats(): array
    {
        // Operator users get their own operator's bet/payout figures (cached per operator).
        $operatorId = AccessControl::operatorId();
        $gameId = $this->gameId();

        // The 6 underlying queries are deterministic for any given second
        // and cheap to share across viewers. A 10s cache cuts DB load by
        // an order of magnitude when multiple admins watch the dashboard.
        [
            'todayRounds' => $todayRounds,
            'totalBet' => $totalBet,
            'totalPayout' => $totalPayout,
            'activeRounds' => $activeRounds,
            'activeOperators' => $activeOperators,
        ] = Cache::remember('admin.kpi-widget:'.($operatorId ?? 'all').':'.($gameId ?? 'all'), 10, function () use ($gameId): array {
            $today = Carbon::today();
            $forGame = fn (Builder $q): Builder => $q->when($gameId, fn (Builder $q, int $id) => $q->where('game_id', $id));

            return [
                'todayRounds' => $forGame(GameRound::query())
                    ->where('created_at', '>=', $today)
                    ->whereIn('state', [RoundState::Settled, RoundState::Crashed])
                    ->count(),
                // Stakes actually taken: refused or rolled-back bets took nothing.
                'totalBet' => (string) $forGame(AccessControl::scopeToOperator(Bet::query()))
                    ->where('placed_at', '>=', $today)
                    ->whereNotIn('status', [BetStatus::Rejected->value, BetStatus::RolledBack->value])
                    ->sum('amount'),
                // Every game's wins, as settled on the bet (crash cashouts included).
                'totalPayout' => (string) $forGame(AccessControl::scopeToOperator(Bet::query()))
                    ->where('status', BetStatus::Won->value)
                    ->where('settled_at', '>=', $today)
                    ->sum('payout_amount'),
                'activeRounds' => $forGame(GameRound::query())
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

        return array_values(array_filter([
            // Operator users only see their own figures — the operator count is platform-wide.
            $operatorId === null ? Stat::make('Active operators', $activeOperators)
                ->icon('heroicon-o-building-office-2')
                ->color('emerald') : null,

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
        ]));
    }

    /** The dashboard's game filter, if it names a game this user may see. */
    private function gameId(): ?int
    {
        $id = $this->pageFilters['game'] ?? null;
        if (! is_numeric($id)) {
            return null;
        }

        return AccessControl::scopeGamesToOperator(Game::query())->whereKey((int) $id)->exists() ? (int) $id : null;
    }

    private function fmt(string $n): string
    {
        return number_format((float) $n, 2);
    }
}
