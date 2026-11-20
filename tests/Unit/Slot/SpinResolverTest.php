<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Models\SugarOrchard;
use App\Domain\ProvablyFair\Services\RandomStream;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

function orchard(): TumbleModel
{
    return SugarOrchard::model();
}

function variant9650(): Variant
{
    return orchard()->variant('v1-9650');
}

it('matches every golden vector from the independent reference implementation', function (): void {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/slot/sugar-orchard-golden.json'), true);
    expect($cases)->toHaveCount(82);

    foreach ($cases as $case) {
        $stream = new RandomStream($case['server_seed'], $case['client_seed'], $case['nonce']);
        $result = SpinResolver::resolve(orchard(), orchard()->variant($case['variant']), $case['mode'], $stream->nextInt(...));

        expect($result->outcome)->toEqual($case['outcome'], "{$case['mode']} {$case['variant']} nonce {$case['nonce']}");
    }
});

it('pays 8+ of a symbol anywhere, then drops the rest and refills each column from the top', function (): void {
    // Columns top to bottom: eight toffees (A) scattered, nothing else reaches eight.
    $grid = str_split('AABCD'.'EAFGA'.'IBACD'.'EFAGH'.'IBCAD'.'EFGHA');
    $refill = str_split('IH'.'BC'.'E'.'D'.'F'.'G');

    $result = SpinResolver::resolve(orchard(), variant9650(), 'base', scripted(variant9650(), [...$grid, ...$refill]));

    $spin = $result->outcome['spins'][0];
    expect($spin['grid'])->toBe('AABCDEAFGAIBACDEFAGHIBCADEFGHA')
        ->and($spin['tumbles'])->toBe([[
            'wins' => [['symbol' => 'A', 'count' => 8, 'win_x' => '15.00']],
            'fill' => ['IH', 'BC', 'E', 'D', 'F', 'G'],
            'fill_mults' => [],
        ]])
        ->and($spin['win_x'])->toBe('15.00')
        ->and($result->win)->toBe(1500)
        ->and($result->triggered)->toBeFalse();
});

it('pays the higher tiers at 10 and 12 of a symbol, and several symbols in one tumble', function (): void {
    // 12 kiwis (I) and 10 lemons (H) in columns 0–3; columns 4–5 never win.
    $grid = str_split('IIIHH'.'IIIHH'.'IIIHH'.'IIIHH'.'HHABC'.'DEFGA');
    $result = SpinResolver::resolve(orchard(), variant9650(), 'base', scripted(variant9650(), $grid));

    expect($result->outcome['spins'][0]['tumbles'][0]['wins'])->toBe([
        ['symbol' => 'H', 'count' => 10, 'win_x' => '1.90'],
        ['symbol' => 'I', 'count' => 12, 'win_x' => '3.75'],
    ]);
});

it('triggers 10 free spins on 4 scatters and pays them; a bought spin places its scatters first', function (): void {
    // Buy: 4 scatters, shuffle indices 0 → cells 0, 1, 2, 3; the rest never win.
    $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', scripted(variant9650(), [4, 0, 0, 0, 0]));

    $first = $result->outcome['spins'][0];
    expect(substr($first['grid'], 0, 4))->toBe('SSSS')
        ->and(substr_count($first['grid'], 'S'))->toBe(4)
        ->and($first['scatter_win_x'])->toBe('2.50')
        ->and($first['free_spins_awarded'])->toBe(10)
        ->and($result->outcome['free_spins'])->toBe(10)
        ->and($result->outcome['spins'])->toHaveCount(11)
        ->and($result->outcome['total_win_x'])->toBe('2.50');
});

it('multiplies a winning free spin by the sum of its multiplier stars', function (): void {
    $buy = [4, 0, 0, 0, 0, ...str_split('ABCDEFGHIABCDEFGHIABCDEFG'), 'H'];
    // First free spin: 8 kiwis, and stars of 2× and 5× (each star's value is drawn right after it).
    $free = ['I', 'I', 'I', 'I', 'M', 2, 'A', 'B', 'C', 'D', 'I', 'I', 'I', 'I', 'M', 5, 'E', 'F', 'G', 'H', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'A', 'B', 'C', 'D'];
    $refill = ['E', 'F', 'G', 'H', 'A', 'B', 'C', 'D'];

    $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', scripted(variant9650(), [...$buy, ...$free, ...$refill]));

    $spin = $result->outcome['spins'][1];
    expect($spin['mults'])->toBe([2, 5])
        ->and($spin['tumbles'][0]['wins'])->toBe([['symbol' => 'I', 'count' => 8, 'win_x' => '0.65']])
        ->and($spin['multiplier'])->toBe(7)
        ->and($spin['win_x'])->toBe('4.55')
        ->and($result->freeSpinsWin)->toBe(455);
});

it('ignores multiplier stars on a free spin that wins nothing', function (): void {
    $buy = [4, 0, 0, 0, 0, ...array_fill(0, 26, null)];
    $free = ['M', 50, ...array_fill(0, 29, null)];

    $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', scripted(variant9650(), [...$buy, ...$free]));

    expect($result->outcome['spins'][1]['mults'])->toBe([50])
        ->and($result->outcome['spins'][1]['multiplier'])->toBe(0)
        ->and($result->outcome['spins'][1]['win_x'])->toBe('0.00');
});

it('adds 5 free spins when 3 scatters land in a free spin', function (): void {
    $buy = [4, 0, 0, 0, 0, ...array_fill(0, 26, null)];
    $free = ['S', 'S', 'S', ...array_fill(0, 27, null)];

    $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', scripted(variant9650(), [...$buy, ...$free]));

    expect($result->outcome['spins'][1]['retrigger'])->toBe(5)
        ->and($result->outcome['free_spins'])->toBe(15);
});

it('stops at the max win of 5,000× and ends the feature there', function (): void {
    $buy = [4, 0, 0, 0, 0, ...array_fill(0, 26, null)];
    // 12 toffees (75×) and a 100× star: 7,500×, capped at 5,000× less the 2.50× already won.
    $free = [...array_fill(0, 12, 'A'), 'M', 100, ...array_fill(0, 17, null)];

    $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', scripted(variant9650(), [...$buy, ...$free]));

    expect($result->maxWinReached)->toBeTrue()
        ->and($result->win)->toBe(500_000)
        ->and($result->outcome['total_win_x'])->toBe('5000.00')
        ->and($result->outcome['spins'][1]['win_x'])->toBe('4997.50')
        ->and($result->outcome['free_spins'])->toBe(1)
        ->and($result->outcome['max_win_reached'])->toBeTrue();
});

it('always gives a bought spin 4 to 6 scatters and at least 10 free spins', function (): void {
    for ($nonce = 0; $nonce < 150; $nonce++) {
        $result = SpinResolver::resolve(orchard(), variant9650(), 'buy', (new RandomStream('buy-check', 'c', $nonce))->nextInt(...));
        $scatters = substr_count($result->outcome['spins'][0]['grid'], 'S');

        expect($scatters)->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(6)
            ->and($result->outcome['free_spins'])->toBeGreaterThanOrEqual(10);
    }
});

it('records the same totals with and without the outcome', function (): void {
    for ($nonce = 0; $nonce < 40; $nonce++) {
        $with = SpinResolver::resolve(orchard(), variant9650(), 'buy', (new RandomStream('rec', 'c', $nonce))->nextInt(...));
        $without = SpinResolver::resolve(orchard(), variant9650(), 'buy', (new RandomStream('rec', 'c', $nonce))->nextInt(...), false);

        expect($without->outcome)->toBeNull()
            ->and([$without->win, $without->baseWin, $without->freeSpinsWin, $without->freeSpins])
            ->toBe([$with->win, $with->baseWin, $with->freeSpinsWin, $with->freeSpins])
            ->and($with->outcome['total_win_x'])->toBe(TumbleModel::x($with->win));
    }
});

it('keeps the measured maths: a fixed-seed run gives exactly the recorded totals', function (string $mode, int $spins, int $expected): void {
    $randomizer = new Randomizer(new Xoshiro256StarStar(20261118));
    $next = fn (int $n): int => $randomizer->getInt(0, $n - 1);
    $total = 0;
    for ($i = 0; $i < $spins; $i++) {
        $total += SpinResolver::resolve(orchard(), variant9650(), $mode, $next, false)->win;
    }

    // Any change to a table, the paytable or the draw order changes these. If that is intended,
    // it is a new variant (never an edit of a live one): re-measure with slot:simulate and update.
    expect($total)->toBe($expected);
})->with([
    'base' => ['base', 20_000, 1_852_590],
    'ante' => ['ante', 20_000, 2_243_760],
    'buy' => ['buy', 400, 3_855_080],
]);

it('refuses an unknown mode', function (): void {
    SpinResolver::resolve(orchard(), variant9650(), 'turbo', fn (int $n): int => 0);
})->throws(InvalidArgumentException::class);
