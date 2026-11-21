<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\LinesResolver;
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
        {--mode=base : base, ante, buy or super (the modes the model has)}
        {--spins=1000000 : Bets to play}
        {--seed=1 : Seed (different seeds give independent runs)}
        {--rng=fast : fast (xoshiro256**) or hmac (production RandomStream)}
        {--processes=1 : Parallel worker processes}
        {--max-win= : The cap on a bet\'s total win, × the bet (default: the model\'s)}
        {--caps= : Also report the RTP at these lower caps (× the bet, comma-separated), from the same bets}
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
        if (! isset($model->variants[$variantId]) || ! in_array($mode, $model->modes(), true) || ! in_array($rng, ['fast', 'hmac'], true)) {
            $this->error('Check --variant ('.implode(', ', array_keys($model->variants)).'), --mode ('.implode(', ', $model->modes()).') and --rng (fast, hmac).');

            return self::FAILURE;
        }

        $spins = max(1, (int) $this->option('spins'));
        $seed = (int) $this->option('seed');
        $processes = max(1, (int) $this->option('processes'));

        // A capped bet wins min(its uncapped win, cap): the running total only grows, and the
        // cap only stops the bet. So one run at the highest cap measures every lower one.
        $cap = $this->option('max-win') ? (int) $this->option('max-win') * 100 : $model->maxWin;
        $caps = array_values(array_filter(array_map(fn (string $c): int => (int) trim($c) * 100, explode(',', (string) $this->option('caps')))));
        if ($cap <= 0 || array_filter($caps, fn (int $c): bool => $c <= 0 || $c > $cap) !== []) {
            $this->error('--max-win must be positive, and every --caps value positive and no higher than it.');

            return self::FAILURE;
        }

        $started = microtime(true);
        $totals = $processes === 1 || $this->option('worker')
            ? $this->simulate($model, $variantId, $mode, $spins, $seed, $rng, $cap, $caps)
            : $this->parallel($code, $variantId, $mode, $spins, $seed, $rng, $processes, $cap, $caps);

        if ($this->option('worker') || $this->option('json')) {
            $this->line((string) json_encode($totals));

            return self::SUCCESS;
        }

        $this->report($model, $variantId, $mode, $rng, $totals, microtime(true) - $started, $cap, $caps);

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $caps
     * @return array<string, int|float|list<int>>
     */
    private function simulate(TumbleModel|LinesModel $model, string $variantId, string $mode, int $spins, int $seed, string $rng, int $cap, array $caps): array
    {
        $variant = $model->variant($variantId);
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $fast = fn (int $n): int => $randomizer->getInt(0, $n - 1);
        $cost = $model->cost($mode, $variant);
        $bounds = array_values(self::BUCKETS);

        $t = ['n' => $spins, 'win' => 0, 'sumSq' => 0.0, 'base' => 0, 'free' => 0, 'hits' => 0, 'triggers' => 0, 'freeSpins' => 0, 'maxed' => 0,
            'biggest' => 0, 'buckets' => array_fill(0, count($bounds), 0), 'capped' => array_fill(0, count($caps), 0)];
        for ($i = 0; $i < $spins; $i++) {
            $next = $rng === 'fast' ? $fast : (new RandomStream("simulation-{$seed}", 'simulation', $i))->nextInt(...);
            $r = $model instanceof LinesModel
                ? LinesResolver::resolve($model, $variant, $mode, $next, false, $cap)
                : SpinResolver::resolve($model, $variant, $mode, $next, false, $cap);
            foreach ($caps as $k => $c) {
                $t['capped'][$k] += min($r->win, $c);
            }

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

    /**
     * @param  list<int>  $caps
     * @return array<string, int|float|list<int>>
     */
    private function parallel(string $code, string $variantId, string $mode, int $spins, int $seed, string $rng, int $processes, int $cap, array $caps): array
    {
        $per = intdiv($spins, $processes);
        $workers = [];
        for ($p = 0; $p < $processes; $p++) {
            $count = $p === $processes - 1 ? $spins - $per * ($processes - 1) : $per;
            $process = new Process([
                PHP_BINARY, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=tracing', '-d', 'opcache.jit_buffer_size=64M', '-d', 'memory_limit=512M',
                base_path('artisan'), 'slot:simulate', $code, "--variant={$variantId}", "--mode={$mode}", "--spins={$count}",
                '--seed='.($seed * 1000 + $p), "--rng={$rng}", '--max-win='.intdiv($cap, 100),
                '--caps='.implode(',', array_map(fn (int $c): int => intdiv($c, 100), $caps)), '--worker',
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

    /**
     * @param  array<string, mixed>  $t
     * @param  list<int>  $caps
     */
    private function report(TumbleModel|LinesModel $model, string $variantId, string $mode, string $rng, array $t, float $seconds, int $cap, array $caps): void
    {
        $n = (int) $t['n'];
        $cost = $model->cost($mode, $model->variant($variantId));
        $rtp = $t['win'] / $n / $cost;
        $sd = sqrt(max(0.0, $t['sumSq'] / $n - $rtp ** 2));
        $ci = 1.96 * $sd / sqrt($n);
        $pct = fn (float $v): string => number_format($v * 100, 3).'%';
        $oneIn = fn (int $k): string => $k > 0 ? '1 in '.number_format($n / $k, 1) : 'never';

        $this->info("{$model->code} {$variantId} · {$mode} · max win ".number_format($cap / 100).'× · '.number_format($n)." bets · rng={$rng} · ".number_format($seconds, 1).'s');
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

        if ($caps !== []) {
            // The difference is measured on the same bets, so it is far more precise than either RTP.
            $this->table(['Max win', 'RTP', 'vs this run'], array_map(fn (int $c, int|float $sum): array => [
                number_format($c / 100).'×',
                $pct($sum / $n / $cost),
                sprintf('%+.4f%%', ($sum - $t['win']) / $n / $cost * 100),
            ], $caps, (array) $t['capped']));
        }
    }
}
