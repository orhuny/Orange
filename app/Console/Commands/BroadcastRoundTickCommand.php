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
        {--interval-ms= : Override config crash.tick_interval_ms}
        {--max-ticks=0 : Exit gracefully after N ticks (0 = forever). Use a wrapper loop for periodic restart.}
        {--memory=512M : Soft memory ceiling (passed to ini_set). Override per-process if php.ini wins.}';

    protected $description = 'Long-running ticker that broadcasts MultiplierTick events for the active running round.';

    public function handle(MultiplierCalculator $calculator): int
    {
        // Long-running CLI hardening.
        $requested = (string) $this->option('memory');
        $iniResult = ini_set('memory_limit', $requested);
        $effective = ini_get('memory_limit');

        DB::connection()->disableQueryLog();
        gc_enable();

        $intervalMs = (int) ($this->option('interval-ms') ?? config('crash.tick_interval_ms', 100));
        $intervalUs = max(1, $intervalMs) * 1000;
        $maxTicks = (int) $this->option('max-ticks');

        $this->info("Tick loop started — interval={$intervalMs}ms · memory_limit={$effective} · max_ticks=".($maxTicks ?: '∞'));
        if ($iniResult === false || $effective !== $requested) {
            $this->warn("ini_set('memory_limit','{$requested}') was rejected by PHP — got '{$effective}' instead.");
            $this->warn('Add `memory_limit=512M` to C:\\php83\\php.ini, or run with `php -d memory_limit=512M artisan crash:tick`.');
        }

        $maxAgeSeconds = 120.0;
        $warned = [];
        $tickCount = 0;
        $gcEvery = 50;           // ~5s at 100ms cadence
        $warnedCap = 64;

        // Reverb's Guzzle broadcast client leaks slowly inside a long-running
        // CLI loop. Exit voluntarily at 70 % of the memory ceiling so the
        // wrapper script (scripts/run-crash-tick.ps1) can restart us before
        // we hit a hard OOM.
        $memoryCeilingBytes = self::parseShorthandBytes($effective ?: '512M');
        $softLimitBytes = (int) ($memoryCeilingBytes * 0.70);

        do {
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

            if ($maxTicks > 0 && $tickCount >= $maxTicks) {
                $this->info("Reached --max-ticks={$maxTicks} — exiting gracefully (memory used: ".self::fmt(memory_get_peak_usage(true)).').');

                return self::SUCCESS;
            }

            // Soft memory ceiling — broadcast pipeline leaks slowly, exit
            // before we hit the hard ini limit.
            if ($tickCount % 100 === 0 && memory_get_usage(true) > $softLimitBytes) {
                $this->warn('Memory soft ceiling reached ('.self::fmt(memory_get_usage(true)).' / '.self::fmt($memoryCeilingBytes).') — exiting for wrapper restart.');

                return self::SUCCESS;
            }

            usleep($intervalUs);
        } while (! $this->shouldStop());

        return self::SUCCESS;
    }

    private static function fmt(int $bytes): string
    {
        $mb = $bytes / 1024 / 1024;

        return number_format($mb, 1).'MB';
    }

    private static function parseShorthandBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return PHP_INT_MAX;
        }
        $unit = strtolower(substr($value, -1));
        $n = (int) $value;

        return match ($unit) {
            'g' => $n * 1024 * 1024 * 1024,
            'm' => $n * 1024 * 1024,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private function shouldStop(): bool
    {
        return false;
    }
}
