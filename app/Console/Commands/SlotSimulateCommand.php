<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\SlotModels;
use App\Domain\ProvablyFair\Services\RandomStream;
use Illuminate\Console\Command;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Process\Process;

/**
 * Measures a slot variant by simulation: RTP (with a 95% interval), how it splits between
 * the base game and free spins, hit rate, free-spin frequency, volatility and the win
 * distribution. The figures in docs/slots/*-maths.md come from here.
 *
 * --rng=hmac draws exactly as production does (RandomStream, a fresh seed pair per run and
 * the bet number as the nonce). --rng=fast uses a seeded xoshiro256** and is ~10× quicker;
 * the RTP is a property of the tables, so both measure the same thing. Neither is ever used
 * to play a bet.
 */
class SlotSimulateCommand extends Command
{
    protected $signature = 'slot:simulate
        {model=sugar-orchard : The maths model}
        {--variant= : The RTP variant (default: the model\'s default)}
        {--mode=base : base, ante or buy}
        {--spins=1000000 : Bets to play}
        {--seed=1 : Seed (different seeds give independent runs)}
        {--rng=fast : fast (xoshiro256**) or hmac (production RandomStream)}
        {--processes=1 : Parallel worker processes}
        {--json : Print the raw totals as JSON}
        {--worker : Internal: run as one worker of a parallel run}';

    protected $description = 'Simulate a slot variant and report its RTP, hit rate, free-spin frequency and win distribution';

    /** Upper bounds (× the stake) of the win-distribution buckets; the last bucket is everything above. */
    private const BUCKETS = ['0' => 0.0, '<1' => 1.0, '1–5' => 5.0, '5–20' => 20.0, '20–100' => 100.0, '100–1k' => 1000.0, '1k–max' => INF];

    public function handle(): int
    {
        $code = (string) $this->argument('model');
        if (! SlotModels::has($code)) {
            $this->error("Unknown model [{$code}]. Known: ".implode(', ', SlotModels::codes()));

            return self::FAILURE;
        }
        $model = SlotModels::get($code);
        $variantId = (string) ($this->option('variant') ?: $model->defaultVariant);
        $mode = (string) $this->option('mode');
        $rng = (string) $this->option('rng');
        if (! isset($model->variants[$variantId]) || ! isset(TumbleModel::COSTS[$mode]) || ! in_array($rng, ['fast', 'hmac'], true)) {
            $this->error('Check --variant ('.implode(', ', array_keys($model->variants)).'), --mode (base, ante, buy) and --rng (fast, hmac).');

            return self::FAILURE;
        }

        $spins = max(1, (int) $this->option('spins'));
        $seed = (int) $this->option('seed');
        $processes = max(1, (int) $this->option('processes'));

        $started = microtime(true);
        $totals = $processes === 1 || $this->option('worker')
            ? $this->simulate($model, $variantId, $mode, $spins, $seed, $rng)
            : $this->parallel($code, $variantId, $mode, $spins, $seed, $rng, $processes);

        if ($this->option('worker') || $this->option('json')) {
            $this->line((string) json_encode($totals));

            return self::SUCCESS;
        }

        $this->report($model, $variantId, $mode, $rng, $totals, microtime(true) - $started);

        return self::SUCCESS;
    }

    /** @return array<string, int|float|list<int>> */
    private function simulate(TumbleModel $model, string $variantId, string $mode, int $spins, int $seed, string $rng): array
    {
        $variant = $model->variant($variantId);
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $fast = fn (int $n): int => $randomizer->getInt(0, $n - 1);
        $cost = TumbleModel::COSTS[$mode];
        $bounds = array_values(self::BUCKETS);

        $t = ['n' => $spins, 'win' => 0, 'sumSq' => 0.0, 'base' => 0, 'free' => 0, 'hits' => 0, 'triggers' => 0, 'freeSpins' => 0, 'maxed' => 0,
            'biggest' => 0, 'buckets' => array_fill(0, count($bounds), 0)];
        for ($i = 0; $i < $spins; $i++) {
            $next = $rng === 'fast' ? $fast : (new RandomStream("simulation-{$seed}", 'simulation', $i))->nextInt(...);
            $r = SpinResolver::resolve($model, $variant, $mode, $next, false);

            $t['win'] += $r->win;
            $t['sumSq'] += ($r->win / $cost) ** 2;
            $t['base'] += $r->baseWin;
            $t['free'] += $r->freeSpinsWin;
            $t['hits'] += $r->win > 0 ? 1 : 0;
            $t['maxed'] += $r->maxWinReached ? 1 : 0;
            $t['biggest'] = max($t['biggest'], $r->win);
            if ($r->triggered) {
                $t['triggers']++;
                $t['freeSpins'] += $r->freeSpins;
            }

            $x = $r->win / $cost;
            foreach ($bounds as $b => $upTo) {
                if ($r->win === 0 ? $b === 0 : ($b > 0 && $x < $upTo)) {
                    $t['buckets'][$b]++;
                    break;
                }
            }
        }

        return $t;
    }

    /** @return array<string, int|float|list<int>> */
    private function parallel(string $code, string $variantId, string $mode, int $spins, int $seed, string $rng, int $processes): array
    {
        $per = intdiv($spins, $processes);
        $workers = [];
        for ($p = 0; $p < $processes; $p++) {
            $count = $p === $processes - 1 ? $spins - $per * ($processes - 1) : $per;
            $process = new Process([
                PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=64M', '-d', 'memory_limit=512M',
                base_path('artisan'), 'slot:simulate', $code, "--variant={$variantId}", "--mode={$mode}", "--spins={$count}",
                '--seed='.($seed * 1000 + $p), "--rng={$rng}", '--worker',
            ]);
            $process->setTimeout(null);
            $process->start();
            $workers[] = $process;
        }

        $totals = null;
        foreach ($workers as $process) {
            $process->wait();
            if (! $process->isSuccessful()) {
                throw new \RuntimeException('A simulation worker failed: '.$process->getErrorOutput().$process->getOutput());
            }
            $lines = preg_split('/\R/', trim($process->getOutput())) ?: [];
            /** @var array<string, int|float|list<int>> $part */
            $part = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
            if ($totals === null) {
                $totals = $part;

                continue;
            }
            foreach ($part as $key => $value) {
                $totals[$key] = match (true) {
                    $key === 'biggest' => max($totals[$key], $value),
                    is_array($value) => array_map(fn ($a, $b) => $a + $b, (array) $totals[$key], $value),
                    default => $totals[$key] + $value,
                };
            }
        }

        return $totals ?? [];
    }

    /** @param  array<string, mixed>  $t */
    private function report(TumbleModel $model, string $variantId, string $mode, string $rng, array $t, float $seconds): void
    {
        $n = (int) $t['n'];
        $cost = TumbleModel::COSTS[$mode];
        $rtp = $t['win'] / $n / $cost;
        $sd = sqrt(max(0.0, $t['sumSq'] / $n - $rtp ** 2));
        $ci = 1.96 * $sd / sqrt($n);
        $pct = fn (float $v): string => number_format($v * 100, 3).'%';
        $oneIn = fn (int $k): string => $k > 0 ? '1 in '.number_format($n / $k, 1) : 'never';

        $this->info("{$model->code} {$variantId} · {$mode} · ".number_format($n)." bets · rng={$rng} · ".number_format($seconds, 1).'s');
        $this->table(['Measure', 'Value'], [
            ['RTP', $pct($rtp).' ± '.$pct($ci).' (95%)'],
            ['  base game', $pct($t['base'] / $n / $cost)],
            ['  free spins', $pct($t['free'] / $n / $cost)],
            ['Volatility (sd per bet, × stake)', number_format($sd, 2)],
            ['Hit rate', $oneIn((int) $t['hits'])],
            ['Free spins', $oneIn((int) $t['triggers'])],
            ['Average free-spins win (× base bet)', $t['triggers'] > 0 ? number_format($t['free'] / $t['triggers'] / 100, 1) : '—'],
            ['Average free spins played', $t['triggers'] > 0 ? number_format($t['freeSpins'] / $t['triggers'], 2) : '—'],
            ['Biggest win (× base bet)', TumbleModel::x((int) $t['biggest'])],
            ['Max win reached', $oneIn((int) $t['maxed'])],
        ]);
        $this->table(array_map(fn (string $label): string => "{$label}×", array_keys(self::BUCKETS)), [
            array_map(fn (int $count): string => number_format($count / $n * 100, 3).'%', (array) $t['buckets']),
        ]);
    }
}
