<?php

declare(strict_types=1);

namespace App\Domain\Game\Listeners;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Services\CashoutService;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class AutoCashoutOnTick
{
    public function __construct(private readonly CashoutService $cashouts) {}

    public function handle(MultiplierTick $event): void
    {
        // Cash out bets whose own threshold was crossed, and bets that reached their max win.
        $multiplier = number_format($event->multiplier, 4, '.', '');
        $bets = Bet::query()
            ->where('round_id', $event->round->id)
            ->where('status', BetStatus::Placed)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->whereNotNull('auto_cashout_at')->where('auto_cashout_at', '<=', $event->multiplier))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('max_payout')
                    ->whereRaw('amount * CAST(? AS DECIMAL(14,4)) >= max_payout', [$multiplier])))
            ->get();

        foreach ($bets as $bet) {
            try {
                $this->cashouts->cashout($bet, isAuto: true);
            } catch (AlreadyCashedOut|RoundNotRunning) {
                // Manual cashout won the race or the round just crashed; safe to ignore.
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
