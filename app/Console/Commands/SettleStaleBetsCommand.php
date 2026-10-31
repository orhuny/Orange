<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Services\InstantPlayService;
use Illuminate\Console\Command;

class SettleStaleBetsCommand extends Command
{
    protected $signature = 'games:settle-stale-bets {--older-than=120 : Seconds a bet must have been pending}';

    protected $description = 'Settle instant bets left pending by a process that died mid-bet (or roll them back if their stake was never confirmed)';

    public function handle(InstantPlayService $instant, EngineRegistry $engines): int
    {
        $handled = $instant->settleStale($engines, (int) $this->option('older-than'));
        $this->info("games:settle-stale-bets — {$handled} bet(s) handled");

        return self::SUCCESS;
    }
}
