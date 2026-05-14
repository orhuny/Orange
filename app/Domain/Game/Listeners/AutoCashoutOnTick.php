<?php

declare(strict_types=1);

namespace App\Domain\Game\Listeners;

use App\Domain\Game\Enums\BetStatus;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Exceptions\AlreadyCashedOut;
use App\Domain\Game\Exceptions\RoundNotRunning;
use App\Domain\Game\Models\Bet;
use App\Domain\Game\Services\CashoutService;
use Throwable;

class AutoCashoutOnTick
{
    public function __construct(private readonly CashoutService $cashouts) {}

    public function handle(MultiplierTick $event): void
    {
        $bets = Bet::query()
            ->where('round_id', $event->round->id)
            ->where('status', BetStatus::Placed)
            ->whereNotNull('auto_cashout_at')
            ->where('auto_cashout_at', '<=', $event->multiplier)
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
