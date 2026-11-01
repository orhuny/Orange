<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Services\BetPlacementService;
use App\Domain\Game\Services\InstantPlayService;
use Illuminate\Console\Command;

class SettleStaleBetsCommand extends Command
{
    protected $signature = 'games:settle-stale-bets {--older-than=120 : Seconds a bet must have been pending}';

    protected $description = 'Resolve bets a process that died mid-bet left pending: confirmed or settled if the stake was taken, rolled back otherwise';

    public function handle(InstantPlayService $instant, BetPlacementService $roundBets, EngineRegistry $engines): int
    {
        $olderThan = (int) $this->option('older-than');
        // Instant bets (no round) and direct-play round bets (crash).
        $handled = $instant->settleStale($engines, $olderThan) + $roundBets->settleStale($olderThan);
        $this->info("games:settle-stale-bets — {$handled} bet(s) handled");

        return self::SUCCESS;
    }
}
