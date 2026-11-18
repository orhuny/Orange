<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Models;

use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;

/**
 * Sugar Orchard: 6×5, pay anywhere (8+), tumbles, 4+ scatters → 10 free spins (+5 for 3+),
 * multiplier stars (2×–100×) in free spins only, max win 5,000×.
 *
 * LIVE TABLES ARE IMMUTABLE. A bet's params name its variant and verification replays it,
 * so never edit a variant once a real bet has used it: add a new id (v2-…) instead.
 * The measured RTP of every variant and mode is in docs/slots/sugar-orchard-maths.md
 * (php artisan slot:simulate).
 */
final class SugarOrchard
{
    public const CODE = 'sugar-orchard';

    private const SYMBOLS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'S', 'M'];

    private const NAMES = ['Toffee', 'Peppermint', 'Gummy bear', 'Truffle', 'Orange', 'Cherries', 'Strawberry', 'Lemon', 'Kiwi', 'Gumball machine (scatter)', 'Sugar star (multiplier)'];

    /** Hundredths of the base bet for 8–9, 10–11 and 12+ of a symbol. */
    private const PAYS = [
        [1500, 3750, 7500],   // A toffee
        [750, 1500, 3750],    // B peppermint
        [450, 1050, 2700],    // C gummy bear
        [300, 750, 1800],     // D truffle
        [240, 480, 1500],     // E orange
        [190, 320, 1200],     // F cherries
        [160, 240, 750],      // G strawberry
        [100, 190, 600],      // H lemon
        [65, 140, 375],       // I kiwi
    ];

    /** Free-spin symbols, the same in every variant. */
    private const FREE = ['A' => 38, 'B' => 52, 'C' => 68, 'D' => 85, 'E' => 116, 'F' => 134, 'G' => 150, 'H' => 162, 'I' => 176, 'S' => 12, 'M' => 100];

    /** Multiplier values from 3× up; the weight of 2× (drawn first) is set per variant. */
    private const MULTIPLIERS = [3 => 175, 4 => 120, 5 => 100, 6 => 80, 8 => 65, 10 => 50, 12 => 38, 15 => 28, 20 => 20, 25 => 14, 50 => 6, 100 => 3];

    private const BUY_SCATTERS = [4 => 80, 5 => 16, 6 => 4];

    /**
     * id => [rtp, base symbols (A–I, S), ante scatter weight (on base × 10), weight of 2×].
     * The buy tables are the base ones without the scatter.
     */
    private const VARIANTS = [
        'v1-9650' => ['0.9650', [454, 604, 754, 904, 1117, 1277, 1397, 1497, 1617, 185], 2185, 317],
        'v1-9550' => ['0.9550', [456, 606, 756, 906, 1115, 1275, 1395, 1495, 1615, 185], 2183, 335],
        'v1-9450' => ['0.9450', [459, 609, 759, 909, 1112, 1272, 1392, 1492, 1612, 185], 2188, 352],
    ];

    private static ?TumbleModel $model = null;

    public static function model(): TumbleModel
    {
        return self::$model ??= self::build();
    }

    private static function build(): TumbleModel
    {
        $free = self::columns(self::weights(self::FREE));
        $buyScatters = new WeightTable(self::BUY_SCATTERS);

        $variants = [];
        foreach (self::VARIANTS as $id => [$rtp, $base, $anteScatter, $twos]) {
            $baseWeights = array_combine(range(0, 9), $base);
            $buyWeights = array_slice($baseWeights, 0, 9, true);
            $anteWeights = array_map(fn (int $w): int => $w * 10, $baseWeights);
            $anteWeights[9] = $anteScatter;

            $variants[$id] = new Variant(
                id: $id,
                rtp: $rtp,
                base: self::columns(new WeightTable($baseWeights)),
                ante: self::columns(new WeightTable($anteWeights)),
                buy: self::columns(new WeightTable($buyWeights)),
                free: $free,
                multipliers: new WeightTable([2 => $twos] + self::MULTIPLIERS),
                buyScatters: $buyScatters,
            );
        }

        return new TumbleModel(
            code: self::CODE,
            name: 'Sugar Orchard',
            cols: 6,
            rows: 5,
            symbols: self::SYMBOLS,
            symbolNames: self::NAMES,
            scatter: 9,
            multiplier: 10,
            payTiers: [8, 10, 12],
            pays: self::PAYS,
            scatterPays: [4 => 250, 5 => 800, 6 => 6000],
            freeSpinsTriggerAt: 4,
            freeSpinsAwarded: 10,
            retriggerAt: 3,
            retriggerAwarded: 5,
            maxFreeSpins: 100,
            maxWin: 500_000,
            variants: $variants,
            defaultVariant: 'v1-9650',
        );
    }

    /**
     * Symbol letters → indices.
     *
     * @param  array<string, int>  $byLetter
     */
    private static function weights(array $byLetter): WeightTable
    {
        $index = array_flip(self::SYMBOLS);
        $weights = [];
        foreach ($byLetter as $letter => $weight) {
            $weights[$index[$letter]] = $weight;
        }

        return new WeightTable($weights);
    }

    /** @return list<WeightTable> the same table on all six columns */
    private static function columns(WeightTable $table): array
    {
        return array_fill(0, 6, $table);
    }
}
