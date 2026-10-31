<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Game\Services\MultiplierCalculator;
use App\Domain\Game\Services\RoundOrchestrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunCrashLoopCommand extends Command
{
    protected $signature = 'crash:loop
        {--game=crash : Game code to run}
        {--rounds=0 : Stop after N rounds (0 = forever)}
        {--betting= : Override betting window (seconds)}
        {--waiting= : Override post-crash pause (seconds)}
        {--memory=512M : Soft memory ceiling (passed to ini_set).}';

    protected $description = 'Drive a continuous crash round loop with realistic timing so the /crash demo page can be observed.';

    public function handle(RoundOrchestrator $orchestrator, MultiplierCalculator $calculator): int
    {
        // Long-running CLI hardening (same recipe as crash:tick).
        $requested = (string) $this->option('memory');
        $iniResult = ini_set('memory_limit', $requested);
        $effective = ini_get('memory_limit');
        DB::connection()->disableQueryLog();
        gc_enable();

        $game = Game::query()->where('code', $this->option('game'))->first();
        if (! $game) {
            $this->error("game '{$this->option('game')}' not found — did you run db:seed?");

            return self::FAILURE;
        }

        $bettingSeconds = (int) ($this->option('betting') ?? config('crash.betting_seconds', 10));
        $waitingSeconds = (int) ($this->option('waiting') ?? config('crash.waiting_seconds', 5));
        $maxRounds = (int) $this->option('rounds');
        $i = 0;

        $this->info("crash:loop on game '{$game->code}' — betting={$bettingSeconds}s waiting={$waitingSeconds}s · memory_limit={$effective}");
        if ($iniResult === false || $effective !== $requested) {
            $this->warn("ini_set('memory_limit','{$requested}') was rejected — got '{$effective}'. Use `php -d memory_limit=512M …` or update php.ini.");
        }

        // This loop is the sole producer of rounds for this game, and the
        // self-restarting wrapper guarantees only one instance runs at a
        // time. So any non-terminal round belonging to this game at boot is,
        // by definition, an orphan from a previous (killed) incarnation —
        // regardless of age. We must settle them immediately: a still-Running
        // orphan would otherwise be picked up by crash:tick (which selects the
        // latest Running round) during the next round's betting window and
        // broadcast a stale, ever-climbing multiplier until its 120s guard.
        $orphans = GameRound::query()
            ->where('game_id', $game->id)
            ->whereIn('state', [RoundState::Waiting, RoundState::Betting, RoundState::Running, RoundState::Crashed])
            ->update(['state' => RoundState::Settled, 'settled_at' => now(), 'updated_at' => now()]);

        if ($orphans > 0) {
            $this->warn("cleaned up {$orphans} orphan round(s) left over from a previous run");
        }

        // Voluntary exit at 70 % of the ceiling so the wrapper script can
        // restart us cleanly before any leak in the broadcast pipeline can
        // hard-OOM the process.
        $memoryCeilingBytes = self::parseShorthandBytes($effective ?: '512M');
        $softLimitBytes = (int) ($memoryCeilingBytes * 0.70);

        while ($maxRounds === 0 || $i < $maxRounds) {
            $i++;
            $round = $orchestrator->create($game);
            $this->line("[{$i}] round {$round->round_uuid} created");

            $orchestrator->openBetting($round, $bettingSeconds);
            $this->line("    → betting open for {$bettingSeconds}s");
            sleep($bettingSeconds);

            $round = $orchestrator->start($round->refresh());
            // No crash point (or the duration it implies) in the log while the round
            // is live — log files are readable by more people than the database.
            $this->line('    → started');

            $duration = $calculator->durationToReach((float) $round->crash_point);
            usleep((int) ($duration * 1_000_000));

            $orchestrator->crash($round->refresh());
            $this->line("    → CRASHED @ {$round->crash_point}x after ".round($duration, 2).'s');
            sleep(1);

            $orchestrator->settle($round->refresh());
            $this->line("    → settled. cooldown {$waitingSeconds}s");
            sleep($waitingSeconds);

            unset($round);
            gc_collect_cycles();

            if (memory_get_usage(true) > $softLimitBytes) {
                $this->warn('Memory soft ceiling reached ('.self::fmt(memory_get_usage(true)).' / '.self::fmt($memoryCeilingBytes).') — exiting for wrapper restart.');

                return self::SUCCESS;
            }
        }

        return self::SUCCESS;
    }

    private static function fmt(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1).'MB';
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
}
