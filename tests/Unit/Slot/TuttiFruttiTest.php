<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\LinesResolver;
use App\Domain\Game\Engines\Slot\Maths\SpinResult;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;
use App\Domain\Game\Engines\Slot\Models\TuttiFrutti;
use App\Domain\ProvablyFair\Services\RandomStream;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/** Tutti Frutti: our first paylines slot (LinesModel, LinesResolver). */
function tutti(): LinesModel
{
    return TuttiFrutti::model();
}

function tutti9650(): Variant
{
    return tutti()->variant('v1-9650');
}

/**
 * A stream that lands each draw where a test wants it. Symbol draws are recognised by their
 * table's total; a wild's value is queued as [set, value] ('base' or 'free'); a bought spin's
 * scatter count as ['count', n]; an int is returned as is (the buy shuffle). A null or an empty
 * queue deals filler that never pays: E, F, G on the first reel (its tables have no kiss, so
 * their totals differ) and H, I, B on the others, so no line runs past the first reel.
 *
 * @param  list<string|int|array{0: string, 1: int}|null>  $queue
 */
function tuttiStream(Variant $variant, array $queue): Closure
{
    $index = array_flip(tutti()->symbols);
    $starts = function (WeightTable $table): array {
        $at = [];
        $sum = 0;
        foreach ($table->weights() as $value => $weight) {
            $at[$value] = $sum;
            $sum += $weight;
        }

        return $at;
    };
    $symbols = [];
    $first = [];
    foreach (['base', 'buy', 'free'] as $set) {
        foreach ($variant->set($set) as $table) {
            $symbols[$table->total] = $starts($table);
        }
        $first[$variant->set($set)[0]->total] = true;
    }
    $wilds = ['base' => $starts($variant->baseMultipliers ?? $variant->multipliers), 'free' => $starts($variant->multipliers)];
    $counts = $starts($variant->buyScatters);
    $cell = 0;

    return function (int $n) use (&$queue, &$cell, $index, $symbols, $first, $wilds, $counts): int {
        $item = array_shift($queue);
        if (is_array($item)) {
            return $item[0] === 'count' ? $counts[$item[1]] : $wilds[$item[0]][$item[1]];
        }
        if (isset($symbols[$n])) {
            $letter = $item ?? (isset($first[$n]) ? 'EFG' : 'HIB')[$cell++ % 3];

            return $symbols[$n][$index[$letter]];
        }

        return (int) $item;
    };
}

/**
 * A screen for the queue: 15 letters, reel by reel (3 each, top to bottom); each W takes the
 * next of $wilds as its value.
 *
 * @param  list<int>  $wilds
 * @return list<string|array{0: string, 1: int}>
 */
function tuttiScreen(string $letters, array $wilds = [], string $set = 'base'): array
{
    $queue = [];
    foreach (str_split(str_replace(' ', '', $letters)) as $letter) {
        $queue[] = $letter;
        if ($letter === 'W') {
            $queue[] = [$set, array_shift($wilds)];
        }
    }

    return $queue;
}

/** @param  list<string|int|array{0: string, 1: int}|null>  $queue */
function tuttiSpin(string $mode, array $queue, ?int $cap = null): SpinResult
{
    return LinesResolver::resolve(tutti(), tutti9650(), $mode, tuttiStream(tutti9650(), $queue), true, $cap);
}

it('matches every golden vector from the independent reference implementation', function (): void {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/slot/tutti-frutti-golden.json'), true);
    expect($cases)->toHaveCount(89);

    foreach ($cases as $case) {
        $stream = new RandomStream($case['server_seed'], $case['client_seed'], $case['nonce']);
        $result = LinesResolver::resolve(tutti(), tutti()->variant($case['variant']), $case['mode'], $stream->nextInt(...), true, $case['cap'] ?? null);

        expect($result->outcome)->toEqual($case['outcome'], "{$case['mode']} {$case['variant']} nonce {$case['nonce']}");
    }
});

it('pays each line left to right from the first reel, and the peach from two', function (): void {
    $result = tuttiSpin('base', tuttiScreen('EAF HAI EAG HBI EFG'));
    $spin = $result->outcome['spins'][0];

    expect($spin['lines'])->toBe([
        ['line' => 1, 'symbol' => 'A', 'count' => 3, 'multiplier' => 1, 'win_x' => '2.50'],
        ['line' => 16, 'symbol' => 'A', 'count' => 2, 'multiplier' => 1, 'win_x' => '0.25'],
        ['line' => 17, 'symbol' => 'A', 'count' => 2, 'multiplier' => 1, 'win_x' => '0.25'],
    ])
        ->and($spin['grid'])->toBe('EAFHAIEAGHBIEFG')
        ->and($result->win)->toBe(300)
        ->and($result->triggered)->toBeFalse();
});

it('lets kisses stand in for any fruit, and adds up the multipliers on a line', function (): void {
    $result = tuttiSpin('base', tuttiScreen('EBF HWI EWG HBI EFG', [2, 3]));
    $spin = $result->outcome['spins'][0];

    // Line 1 is B, 2×, 3×, B: four pineapples times 2 + 3. Lines 10, 11 and 14 run through the kisses too.
    expect($spin['lines'])->toBe([
        ['line' => 1, 'symbol' => 'B', 'count' => 4, 'multiplier' => 5, 'win_x' => '37.50'],
        ['line' => 10, 'symbol' => 'E', 'count' => 3, 'multiplier' => 5, 'win_x' => '3.75'],
        ['line' => 11, 'symbol' => 'F', 'count' => 3, 'multiplier' => 5, 'win_x' => '2.50'],
        ['line' => 14, 'symbol' => 'E', 'count' => 3, 'multiplier' => 2, 'win_x' => '1.50'],
    ])
        ->and($spin['wilds'])->toBe([2, 3])
        ->and($result->win)->toBe(4525);
});

it('never multiplies with a plain kiss', function (): void {
    $spin = tuttiSpin('base', tuttiScreen('ECF HWI ECG HIB EFG', [1]))->outcome['spins'][0];

    expect($spin['lines'][0])->toBe(['line' => 1, 'symbol' => 'C', 'count' => 3, 'multiplier' => 1, 'win_x' => '1.50']);
});

it('pays scatters anywhere, awards free spins by their count, and grows the amore with every kiss', function (): void {
    // 3 cocktails: 2× and 10 free spins. The first free spin lands a 3× kiss: the amore becomes 2,
    // and it doubles that spin's line wins (11.25×); the other nine pay nothing.
    $result = tuttiSpin('base', [...tuttiScreen('SEF HIB SFG HIB SFG'), ...tuttiScreen('EAF HWI EAG HBI EFG', [3], 'free')]);
    $spins = $result->outcome['spins'];

    expect($spins[0]['scatter_win_x'])->toBe('2.00')
        ->and($spins[0]['free_spins_awarded'])->toBe(10)
        ->and($spins)->toHaveCount(11)
        ->and($spins[1]['amore'])->toBe(2)
        ->and($spins[1]['line_win_x'])->toBe('11.25')
        ->and($spins[1]['win_x'])->toBe('22.50')
        ->and($spins[10]['amore'])->toBe(2)
        ->and($result->outcome['free_spins_win_x'])->toBe('22.50')
        ->and($result->win)->toBe(2450);

    $five = tuttiSpin('base', tuttiScreen('SEF HIS SFG HIS SFG'))->outcome['spins'][0];
    expect([$five['scatter_win_x'], $five['free_spins_awarded']])->toBe(['50.00', 15]);
});

it('never multiplies a free spin\'s scatter pay by the amore, and retriggers on 3 cocktails', function (): void {
    $result = tuttiSpin('base', [...tuttiScreen('SEF HIB SFG HIB SFG'), ...tuttiScreen('SEF HIB SFG HWB SWG', [1, 1], 'free')]);
    $spin = $result->outcome['spins'][1];

    expect($spin['amore'])->toBe(3)
        ->and($spin['scatter_win_x'])->toBe('2.00')
        ->and($spin['win_x'])->toBe('2.00')
        ->and($spin['retrigger'])->toBe(5)
        ->and($result->freeSpins)->toBe(15);
});

it('places a bought spin\'s scatters first, then fills the rest from the buy tables', function (): void {
    // 4 scatters at cells 0–3 (reel 0 and the top of reel 1), the other 11 cells never pay.
    $queue = [['count', 4], 0, 0, 0, 0, ...str_split('IB'.'EFG'.'HIB'.'EFG')];
    $result = tuttiSpin('buy', $queue);

    expect($result->outcome['spins'][0]['grid'])->toBe('SSSSIBEFGHIBEFG')
        ->and($result->outcome['spins'][0]['free_spins_awarded'])->toBe(12)
        ->and($result->outcome['mode'])->toBe('buy')
        ->and($result->freeSpins)->toBe(12);
});

it('always gives a bought spin 3 to 5 scatters and at least 10 free spins', function (): void {
    for ($nonce = 0; $nonce < 300; $nonce++) {
        $result = LinesResolver::resolve(tutti(), tutti9650(), 'buy', (new RandomStream('tutti-buy', 'client', $nonce))->nextInt(...));
        $scatters = $result->outcome['spins'][0]['scatters'];

        expect($scatters)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5)
            ->and($result->triggered)->toBeTrue()
            ->and($result->freeSpins)->toBeGreaterThanOrEqual(10);
    }
});

it('stops at the cap the bet was played with, and skips the free spins', function (): void {
    $result = tuttiSpin('base', tuttiScreen('SAF HAS SAG HBI SFG'), 400);

    expect($result->win)->toBe(400)
        ->and($result->maxWinReached)->toBeTrue()
        ->and($result->triggered)->toBeFalse()
        ->and($result->outcome['spins'][0]['free_spins_awarded'])->toBe(0);
});

it('records the same totals with and without the outcome', function (): void {
    foreach (['base', 'buy'] as $mode) {
        for ($nonce = 0; $nonce < 150; $nonce++) {
            $with = LinesResolver::resolve(tutti(), tutti9650(), $mode, (new RandomStream('tutti-rec', 'c', $nonce))->nextInt(...));
            $without = LinesResolver::resolve(tutti(), tutti9650(), $mode, (new RandomStream('tutti-rec', 'c', $nonce))->nextInt(...), false);

            expect([$without->win, $without->baseWin, $without->freeSpinsWin, $without->freeSpins, $without->outcome])
                ->toBe([$with->win, $with->baseWin, $with->freeSpinsWin, $with->freeSpins, null]);
        }
    }
});

it('has no ante and no super buy: a base spin, and a buy at 100×', function (): void {
    expect(tutti()->modes())->toBe(['base', 'buy'])
        ->and(tutti()->cost('buy', tutti9650()))->toBe(10_000)
        ->and(tutti()->superBuyTide)->toBeNull()
        ->and(fn () => LinesResolver::resolve(tutti(), tutti9650(), 'ante', fn (int $n): int => 0))->toThrow(InvalidArgumentException::class);
});

it('offers the measured caps, each with its RTP, the default with the variant\'s own', function (): void {
    foreach (tutti()->variants as $variant) {
        $choices = tutti()->maxWinChoices($variant);

        expect(array_keys($choices))->toBe([100_000, 200_000, 250_000, 500_000, 1_000_000, 1_500_000, 2_000_000])
            ->and($choices[500_000])->toBe($variant->rtp);
    }
});

it('refuses a wild on the first reel, a line off the screen and two lines on the same rows', function (Closure $change): void {
    $variant = tutti9650();
    $args = [
        'code' => 't', 'name' => 'T', 'cols' => 5, 'rows' => 3, 'symbols' => tutti()->symbols, 'symbolNames' => tutti()->symbolNames,
        'scatter' => 9, 'wild' => 10, 'lines' => tutti()->lines, 'pays' => tutti()->pays, 'scatterPays' => tutti()->scatterPays,
        'freeSpinsAwarded' => tutti()->freeSpinsAwarded, 'retriggerAt' => 3, 'retriggerAwarded' => 5, 'maxFreeSpins' => 100,
        'maxWin' => 500_000, 'variants' => ['v' => $variant], 'defaultVariant' => 'v',
    ];

    expect(fn () => new LinesModel(...$change($args, $variant)))->toThrow(InvalidArgumentException::class);
})->with([
    'a wild on reel 0' => [function (array $args, Variant $v): array {
        $wildFirst = new WeightTable([0 => 10, 10 => 5]);

        return ['variants' => ['v' => new Variant('v', '0.9650', [$wildFirst, ...array_slice($v->base, 1)], $v->ante, $v->buy, $v->free,
            $v->multipliers, $v->buyScatters, $v->baseMultipliers)]] + $args;
    }],
    'a line off the screen' => [fn (array $args): array => ['lines' => [[0, 1, 3, 1, 0]]] + $args],
    'two lines on the same rows' => [fn (array $args): array => ['lines' => [[1, 1, 1, 1, 1], [1, 1, 1, 1, 1]]] + $args],
]);

it('keeps the measured maths: a fixed-seed run gives exactly the recorded totals', function (string $mode, int $spins, int $expected): void {
    $randomizer = new Randomizer(new Xoshiro256StarStar(20261121));
    $next = fn (int $n): int => $randomizer->getInt(0, $n - 1);
    $total = 0;
    for ($i = 0; $i < $spins; $i++) {
        $total += LinesResolver::resolve(tutti(), tutti9650(), $mode, $next, false)->win;
    }

    expect($total)->toBe($expected);
})->with([
    'base' => ['base', 20_000, 1_935_475],
    'buy' => ['buy', 300, 3_183_325],
]);
