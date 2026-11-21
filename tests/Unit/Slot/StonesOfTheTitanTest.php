<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\SpinResolver;
use App\Domain\Game\Engines\Slot\Maths\SpinResult;
use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;
use App\Domain\Game\Engines\Slot\Models\StonesOfTheTitan;
use App\Domain\Game\Engines\Slot\Models\SugarOrchard;
use App\Domain\Game\Engines\Slot\Models\TridentOfPoseidon;
use App\Domain\ProvablyFair\Services\RandomStream;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/** Stones of the Titan: Trident's tables under its own name, and the Super Eclipse buy. */
function titan(): TumbleModel
{
    return StonesOfTheTitan::model();
}

function titan9650(): Variant
{
    return titan()->variant('v1-9650');
}

/** @param  list<string|int|null>  $queue */
function titanSpin(string $mode, array $queue, ?int $cap = null): SpinResult
{
    return SpinResolver::resolve(titan(), titan9650(), $mode, scripted(titan9650(), $queue, titan()), true, $cap);
}

/** A bought spin: 4 scatters at cells 0–3, the other 26 cells never win. */
function titanBought(): array
{
    return [4, 0, 0, 0, 0, ...array_fill(0, 26, null)];
}

/**
 * A free spin of 8 comet shards (0.35×) with an optional meteor, then 8 refills that never win.
 *
 * @return list<string|int|null>
 */
function titanCometSpin(?int $meteor = null): array
{
    $first = $meteor === null ? ['I', 'I', 'I', 'I', 'A'] : ['I', 'I', 'I', 'I', 'M', $meteor];

    return [...$first, 'I', 'I', 'I', 'I', 'B', ...str_split('CDEFG'.'HACDE'.'FGHAC'.'DEFGH'), ...array_fill(0, 8, null)];
}

it('matches every golden vector from the independent reference implementation', function (): void {
    $cases = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/slot/stones-of-the-titan-golden.json'), true);
    expect($cases)->toHaveCount(71);

    foreach ($cases as $case) {
        $stream = new RandomStream($case['server_seed'], $case['client_seed'], $case['nonce']);
        $result = SpinResolver::resolve(titan(), titan()->variant($case['variant']), $case['mode'], $stream->nextInt(...), true, $case['cap'] ?? null);

        expect($result->outcome)->toEqual($case['outcome'], "{$case['mode']} {$case['variant']} nonce {$case['nonce']}");
    }
});

it('draws exactly as Trident of Poseidon does, so Trident\'s measurements hold', function (): void {
    expect(array_keys(titan()->variants))->toBe(array_keys(TridentOfPoseidon::model()->variants))
        ->and(titan()->pays)->toBe(TridentOfPoseidon::model()->pays)
        ->and(titan()->scatterPays)->toBe(TridentOfPoseidon::model()->scatterPays)
        ->and(titan()->maxWin)->toBe(TridentOfPoseidon::model()->maxWin);

    foreach (titan()->variants as $id => $variant) {
        $trident = TridentOfPoseidon::model()->variant($id);
        expect($variant->rtp)->toBe($trident->rtp)
            ->and($variant->rtpByCap)->toBe($trident->rtpByCap);
        foreach (['base', 'ante', 'buy', 'free'] as $set) {
            expect(array_map(fn (WeightTable $t): array => $t->weights(), $variant->set($set)))
                ->toBe(array_map(fn (WeightTable $t): array => $t->weights(), $trident->set($set)), "{$id} {$set}");
        }
    }

    foreach (['base', 'ante', 'buy'] as $mode) {
        for ($nonce = 0; $nonce < 20; $nonce++) {
            $ours = SpinResolver::resolve(titan(), titan9650(), $mode, (new RandomStream('titan-twin', 'c', $nonce))->nextInt(...));
            $theirs = SpinResolver::resolve(TridentOfPoseidon::model(), TridentOfPoseidon::model()->variant('v1-9650'), $mode, (new RandomStream('titan-twin', 'c', $nonce))->nextInt(...));

            expect(['model' => 'trident-of-poseidon'] + $ours->outcome)->toEqual($theirs->outcome);
        }
    }
});

it('names its own symbols', function (): void {
    expect(titan()->code)->toBe('stones-of-the-titan')
        ->and(titan()->name)->toBe('Stones of the Titan')
        ->and(titan()->symbolNames[0])->toBe('Caged star')
        ->and(titan()->symbolNames[9])->toBe('Eclipse (scatter)')
        ->and(titan()->symbolNames[10])->toBe('Meteor (multiplier)');
});

it('starts a super buy\'s Eclipse at 10: every winning free spin is multiplied from the first', function (): void {
    // 1: 8 comet shards (0.35×), no meteor → Eclipse 10, pays 3.50.
    // 2: 8 comet shards with a 5× meteor → Eclipse 15, pays 5.25.
    $result = titanSpin('super', [...titanBought(), ...titanCometSpin(), ...titanCometSpin(5)]);
    $spins = $result->outcome['spins'];

    expect($result->outcome['mode'])->toBe('super')
        ->and($result->outcome['tide_start'])->toBe(10)
        ->and(array_map(fn (array $s): int => $s['tide'], array_slice($spins, 1, 3)))->toBe([10, 15, 15])
        ->and(array_map(fn (array $s): string => $s['win_x'], array_slice($spins, 1, 3)))->toBe(['3.50', '5.25', '0.00'])
        ->and($result->outcome['free_spins'])->toBe(15)
        ->and($result->outcome['total_win_x'])->toBe('12.75');   // 4.00 scatters + 3.50 + 5.25

    // The same draws as a plain buy start at 0: the first spin pays unmultiplied.
    $buy = titanSpin('buy', [...titanBought(), ...titanCometSpin(), ...titanCometSpin(5)]);
    expect(array_map(fn (array $s): int => $s['tide'], array_slice($buy->outcome['spins'], 1, 2)))->toBe([0, 5])
        ->and(array_map(fn (array $s): string => $s['win_x'], array_slice($buy->outcome['spins'], 1, 2)))->toBe(['0.35', '1.75'])
        ->and($buy->outcome)->not->toHaveKey('tide_start');
});

it('always gives a super buy 4 to 6 scatters and at least 15 free spins', function (): void {
    for ($nonce = 0; $nonce < 60; $nonce++) {
        $result = SpinResolver::resolve(titan(), titan9650(), 'super', (new RandomStream('titan-super', 'c', $nonce))->nextInt(...));
        $scatters = substr_count($result->outcome['spins'][0]['grid'], 'S');

        expect($scatters)->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(6)
            ->and($result->maxWinReached || $result->outcome['free_spins'] >= 15)->toBeTrue()
            ->and($result->outcome['spins'][1]['tide'])->toBeGreaterThanOrEqual(10);
    }
});

it('stops a super buy at the cap it was played with', function (): void {
    $result = titanSpin('super', [...titanBought(), ...titanCometSpin(), ...titanCometSpin(5)], 1_000);

    expect($result->maxWinReached)->toBeTrue()
        ->and($result->win)->toBe(1_000)
        ->and($result->outcome['free_spins'])->toBe(2);   // 4.00 + 3.50 = 7.50, then 5.25 would pass 10×
});

it('prices the super buy per variant, and only this model offers one', function (): void {
    expect(titan()->modes())->toBe(['base', 'ante', 'buy', 'super'])
        ->and(TridentOfPoseidon::model()->modes())->toBe(['base', 'ante', 'buy'])
        ->and(SugarOrchard::model()->modes())->toBe(['base', 'ante', 'buy'])
        ->and(fn () => SpinResolver::resolve(TridentOfPoseidon::model(), TridentOfPoseidon::model()->variant('v1-9650'), 'super', fn (int $n): int => 0))
        ->toThrow(InvalidArgumentException::class);

    foreach (titan()->variants as $variant) {
        // Whole bets, so the stake of any bet is exact.
        expect(titan()->cost('super', $variant))->toBe($variant->superCost)
            ->and($variant->superCost % 100)->toBe(0)
            ->and($variant->superCost)->toBeGreaterThan(10_000)
            ->and(titan()->cost('buy', $variant))->toBe(10_000);
    }
});

it('refuses a super buy without TIDE free spins or without a price on every variant', function (): void {
    $trident = TridentOfPoseidon::model();
    $make = fn (array $variants, string $multiplier = TumbleModel::TIDE): TumbleModel => new TumbleModel(
        code: 'x', name: 'x', cols: 6, rows: 5, symbols: $trident->symbols, symbolNames: $trident->symbolNames,
        scatter: 9, multiplier: 10, payTiers: [8, 10, 12], pays: $trident->pays, scatterPays: $trident->scatterPays,
        freeSpinsTriggerAt: 4, freeSpinsAwarded: 15, retriggerAt: 3, retriggerAwarded: 5, maxFreeSpins: 100, maxWin: 500_000,
        variants: $variants, defaultVariant: 'v1-9650', baseMultipliers: true, freeSpinMultiplier: $multiplier, superBuyTide: 10,
    );

    expect(fn () => $make(['v1-9650' => $trident->variant('v1-9650')]))->toThrow(InvalidArgumentException::class, 'super buy price')
        ->and(fn () => $make(['v1-9650' => titan9650()], TumbleModel::SPIN))->toThrow(InvalidArgumentException::class, 'TIDE')
        ->and($make(['v1-9650' => titan9650()])->modes())->toContain('super');
});

it('keeps the measured maths: a fixed-seed run gives exactly the recorded totals', function (string $mode, int $spins, int $expected): void {
    $randomizer = new Randomizer(new Xoshiro256StarStar(20261120));
    $next = fn (int $n): int => $randomizer->getInt(0, $n - 1);
    $total = 0;
    for ($i = 0; $i < $spins; $i++) {
        $total += SpinResolver::resolve(titan(), titan9650(), $mode, $next, false)->win;
    }

    // Base, ante and buy are Trident's recorded totals (TridentOfPoseidonTest): the same tables.
    // Any change to a table, the paytable or the draw order changes these. If that is intended,
    // it is a new variant (never an edit of a live one): re-measure with slot:simulate and update.
    expect($total)->toBe($expected);
})->with([
    'base' => ['base', 20_000, 1_868_020],
    'ante' => ['ante', 20_000, 2_511_385],
    'buy' => ['buy', 400, 4_051_260],
    'super' => ['super', 400, 7_164_480],
]);
