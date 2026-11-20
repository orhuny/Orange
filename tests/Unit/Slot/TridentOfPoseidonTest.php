<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\SpinResult;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Models\TridentOfPoseidon;
use App\Domain\ProvablyFair\Services\RandomStream;

/** Trident of Poseidon: pearls in the base game, the Tide in free spins, and a pickable cap. */
function trident(): TumbleModel
{
    return TridentOfPoseidon::model();
}

function trident9650(): Variant
{
    return trident()->variant('v1-9650');
}

/** @param  list<string|int|null>  $queue */
function tridentSpin(string $mode, array $queue, ?int $cap = null): SpinResult
{
    return SpinResolver::resolve(trident(), trident9650(), $mode, scripted(trident9650(), $queue, trident()), true, $cap);
}

it('matches every golden vector from the independent reference implementation', function (): void {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/slot/trident-of-poseidon-golden.json'), true);
    expect($cases)->toHaveCount(114);

    foreach ($cases as $case) {
        $stream = new RandomStream($case['server_seed'], $case['client_seed'], $case['nonce']);
        $result = SpinResolver::resolve(trident(), trident()->variant($case['variant']), $case['mode'], $stream->nextInt(...), true, $case['cap'] ?? null);

        expect($result->outcome)->toEqual($case['outcome'], "{$case['mode']} {$case['variant']} nonce {$case['nonce']}");
    }
});

it('multiplies a base spin\'s line win by its pearls, but never the scatter pay', function (): void {
    // 8 nautiluses (0.35×), pearls of 5× and 3×, 4 scatters; nothing else reaches 8.
    $grid = ['I', 'I', 'I', 'I', 'M', 5, 'I', 'I', 'I', 'I', 'M', 3, 'S', 'S', 'S', 'S', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    $result = tridentSpin('base', $grid);
    $first = $result->outcome['spins'][0];

    expect($first['mults'])->toBe([5, 3])
        ->and($first['tumbles'][0]['wins'])->toBe([['symbol' => 'I', 'count' => 8, 'win_x' => '0.35']])
        ->and($first['multiplier'])->toBe(8)
        ->and($first['scatter_win_x'])->toBe('4.00')
        ->and($first['win_x'])->toBe('6.80')        // 0.35 × 8 + 4.00
        ->and($first['free_spins_awarded'])->toBe(15)
        ->and($result->outcome['free_spins'])->toBe(15)
        ->and($result->outcome['total_win_x'])->toBe('6.80');
});

it('ignores base pearls on a spin that wins nothing', function (): void {
    $result = tridentSpin('base', ['M', 100]);

    expect($result->outcome['spins'][0]['mults'])->toBe([100])
        ->and($result->outcome['spins'][0]['multiplier'])->toBe(0)
        ->and($result->win)->toBe(0);
});

it('builds the Tide in free spins: each winning spin\'s pearls join it, and it multiplies every winning spin after', function (): void {
    // Buy: 4 scatters at cells 0–3, the other 26 cells never win.
    $buy = [4, 0, 0, 0, 0, ...array_fill(0, 26, null)];
    // 1: 8 nautiluses (0.35×) with pearls 2× + 3× → Tide 5, pays 1.75.
    $fs1 = ['I', 'I', 'I', 'I', 'M', 2, 'I', 'I', 'I', 'I', 'M', 3, ...str_split('ABCDE'.'FGHAB'.'CDEFG'.'HABCD'), ...array_fill(0, 8, null)];
    // 2: 8 starfish (0.60×), no pearls → the Tide (5) still multiplies: 3.00.
    $fs2 = [...str_split('HHHHA'.'HHHHB'.'ABCDE'.'FGABC'.'DEFGA'.'BCDEF'), ...array_fill(0, 8, null)];
    // 3: a 50× pearl on a spin that wins nothing → the Tide stays 5.
    $fs3 = ['M', 50, ...str_split('ABCD'.'EFGHA'.'BCDEF'.'GHIAB'.'CDEFG'.'HIABC')];
    // 4: 8 turtles (0.90×) with a 10× pearl → Tide 15, pays 13.50.
    $fs4 = ['G', 'G', 'G', 'G', 'M', 10, ...str_split('GGGGA'.'BCDEF'.'HIABC'.'DEFHI'.'ABCDE'), ...array_fill(0, 8, null)];

    $result = tridentSpin('buy', [...$buy, ...$fs1, ...$fs2, ...$fs3, ...$fs4]);
    $spins = $result->outcome['spins'];

    expect(array_map(fn (array $s): int => $s['multiplier'], array_slice($spins, 1, 4)))->toBe([5, 0, 0, 10])
        ->and(array_map(fn (array $s): int => $s['tide'], array_slice($spins, 1, 5)))->toBe([5, 5, 5, 15, 15])
        ->and(array_map(fn (array $s): string => $s['win_x'], array_slice($spins, 1, 5)))->toBe(['1.75', '3.00', '0.00', '13.50', '0.00'])
        ->and($result->outcome['free_spins'])->toBe(15)
        ->and($result->outcome['total_win_x'])->toBe('22.25');   // 4.00 scatters + 1.75 + 3.00 + 13.50
});

it('gives a pearl in a bought spin its value', function (): void {
    $result = tridentSpin('buy', [4, 0, 0, 0, 0, 'M', 25, ...array_fill(0, 25, null)]);

    expect($result->outcome['spins'][0]['mults'])->toBe([25])
        ->and($result->outcome['spins'][0]['multiplier'])->toBe(0);
});

it('stops at the cap the bet was played with, and skips the free spins', function (): void {
    // 12 hippocampi (50×) and a 100× pearl, with 4 scatters: 5,004× uncapped.
    $grid = [...array_fill(0, 12, 'A'), 'M', 100, 'S', 'S', 'S', 'S', ...array_fill(0, 13, null)];

    $capped = tridentSpin('base', $grid, 200_000);

    expect($capped->maxWinReached)->toBeTrue()
        ->and($capped->win)->toBe(200_000)
        ->and($capped->outcome['spins'][0]['free_spins_awarded'])->toBe(0)
        ->and($capped->outcome['free_spins'])->toBe(0)
        ->and(tridentSpin('base', $grid)->win)->toBe(500_000);
});

it('lists the default cap first among a variant\'s choices, with the variant\'s own RTP', function (): void {
    $choices = trident()->maxWinChoices(trident9650());

    expect($choices[500_000])->toBe('0.9650')
        ->and(trident()->rtp(trident9650(), 500_000))->toBe('0.9650')
        ->and(fn () => trident()->rtp(trident9650(), 123_400))->toThrow(InvalidArgumentException::class);
});

it('always gives a bought spin 4 to 6 scatters and at least 15 free spins', function (): void {
    for ($nonce = 0; $nonce < 100; $nonce++) {
        $result = SpinResolver::resolve(trident(), trident9650(), 'buy', (new RandomStream('trident-buy', 'c', $nonce))->nextInt(...));
        $scatters = substr_count($result->outcome['spins'][0]['grid'], 'S');

        expect($scatters)->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(6)
            ->and($result->maxWinReached || $result->outcome['free_spins'] >= 15)->toBeTrue();
    }
});

it('records the same totals with and without the outcome', function (): void {
    for ($nonce = 0; $nonce < 30; $nonce++) {
        $with = SpinResolver::resolve(trident(), trident9650(), 'buy', (new RandomStream('trident-rec', 'c', $nonce))->nextInt(...));
        $without = SpinResolver::resolve(trident(), trident9650(), 'buy', (new RandomStream('trident-rec', 'c', $nonce))->nextInt(...), false);

        expect($without->outcome)->toBeNull()
            ->and([$without->win, $without->baseWin, $without->freeSpinsWin, $without->freeSpins])
            ->toBe([$with->win, $with->baseWin, $with->freeSpinsWin, $with->freeSpins])
            ->and($with->outcome['total_win_x'])->toBe(TumbleModel::x($with->win));
    }
});
