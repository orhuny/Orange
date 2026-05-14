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

        // Anything older than this is treated as an orphan and skipped — no
        // crash round legitimately runs longer than ~120s under the default
        // growth curve, and a stuck round was previously firing infinite
        // ticks at the capped crash_point.
        $maxAgeSeconds = 120.0;
        $warned = [];

        do {
            $round = GameRound::query()
                ->where('state', RoundState::Running)
                ->latest('started_at')
                ->first();

            if ($round instanceof GameRound) {
                $now = Carbon::now();
                $elapsedSeconds = abs($round->started_at->diffInMicroseconds($now)) / 1_000_000;

                if ($elapsedSeconds > $maxAgeSeconds) {
                    // Orphan — let the operator know once and skip until the
                    // next crash:loop cleanup picks it up.
                    if (! isset($warned[$round->id])) {
                        $this->warn("skipping stale round #{$round->id} (started {$elapsedSeconds}s ago)");
                        $warned[$round->id] = true;
                    }
                } else {
                    $multiplier = $calculator->forRound($round, $now);
                    MultiplierTick::dispatch($round, $multiplier, $elapsedSeconds);
                }
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
