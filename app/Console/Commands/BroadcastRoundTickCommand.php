<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Events\MultiplierTick;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BroadcastRoundTickCommand extends Command
{
    protected $signature = 'crash:tick
        {--once : Emit a single tick for the active round and exit}
        {--interval-ms= : Override config crash.tick_interval_ms}';

    protected $description = 'Long-running ticker that broadcasts MultiplierTick events for the active running round.';

    public function handle(MultiplierCalculator $calculator): int
    {
        // Long-running CLI hardening: bump memory ceiling, kill the query log
        // (Laravel pins every executed query in memory by default for debug),
        // and run periodic GC to release stale Eloquent / Carbon instances.
        ini_set('memory_limit', '256M');
        DB::connection()->disableQueryLog();
        gc_enable();

        $intervalMs = (int) ($this->option('interval-ms') ?? config('crash.tick_interval_ms', 100));
        $intervalUs = max(1, $intervalMs) * 1000;

        $this->info("Tick loop started (interval={$intervalMs}ms). Ctrl+C to stop.");

        $maxAgeSeconds = 120.0;
        $warned = [];
        $tickCount = 0;
        $gcEvery = 200;          // ~20s at 100ms cadence
        $warnedCap = 64;         // bounded — drop oldest entries past this

        do {
            // Lean SELECT — only the columns needed to compute a tick.
            $round = GameRound::query()
                ->where('state', RoundState::Running)
                ->latest('started_at')
                ->first(['id', 'round_uuid', 'state', 'started_at', 'crashed_at', 'crash_point']);

            if ($round instanceof GameRound) {
                $now = Carbon::now();
                $elapsedSeconds = abs($round->started_at->diffInMicroseconds($now)) / 1_000_000;

                if ($elapsedSeconds > $maxAgeSeconds) {
                    if (! isset($warned[$round->id])) {
                        $this->warn("skipping stale round #{$round->id} (started {$elapsedSeconds}s ago)");
                        $warned[$round->id] = true;
                        if (count($warned) > $warnedCap) {
                            array_shift($warned);
                        }
                    }
                } else {
                    $multiplier = $calculator->forRound($round, $now);
                    MultiplierTick::dispatch($round, $multiplier, $elapsedSeconds);
                }
            }

            unset($round);

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            $tickCount++;
            if ($tickCount % $gcEvery === 0) {
                gc_collect_cycles();
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
