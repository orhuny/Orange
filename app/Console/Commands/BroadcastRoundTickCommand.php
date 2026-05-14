<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class BroadcastRoundTickCommand extends Command
{
    protected $signature = 'crash:tick
        {--once : Emit a single tick for the active round and exit}
        {--interval-ms= : Override config crash.tick_interval_ms}';

    protected $description = 'Long-running ticker that broadcasts MultiplierTick events for the active running round.';

    public function handle(MultiplierCalculator $calculator): int
    {
        $intervalMs = (int) ($this->option('interval-ms') ?? config('crash.tick_interval_ms', 100));
        $intervalUs = max(1, $intervalMs) * 1000;

        $this->info("Tick loop started (interval={$intervalMs}ms). Ctrl+C to stop.");

        do {
            $round = GameRound::query()
                ->where('state', RoundState::Running)
                ->latest('started_at')
                ->first();

            if ($round instanceof GameRound) {
                $now = Carbon::now();
                $elapsed = max(0.0, $now->diffInMicroseconds($round->started_at) / -1_000_000);
                $multiplier = $calculator->forRound($round, $now);

                MultiplierTick::dispatch($round, $multiplier, abs($elapsed));
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            usleep($intervalUs);
        } while (! $this->shouldStop());

        return self::SUCCESS;
    }

    private function shouldStop(): bool
    {
        return false;
    }
}
