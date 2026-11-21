<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Models;

use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;

/**
 * Stones of the Titan: 6×5, pay anywhere (8+), tumbles. Meteors (multipliers) land in the
 * base game (2×–100×) and in free spins (2×–500×). 4+ scatters → 15 free spins (+5 for 3+),
 * where every winning spin's meteors join the Eclipse, which multiplies each winning spin
 * from then on. Max win 5,000× by default; a game may pick another cap its variant was
 * measured with (Variant::$rtpByCap).
 *
 * The tables are a copy of Trident of Poseidon's v1 tables, so they draw exactly as Trident
 * does and its measurements hold (StonesOfTheTitanTest checks that). They are this model's
 * own from here on: a retune of either game never touches the other.
 *
 * LIVE TABLES ARE IMMUTABLE. A bet's params name its variant and cap, and verification
 * replays them, so never edit a variant once a real bet has used it: add a new id (v2-…)
 * instead. The measured RTP of every variant, mode and cap is in
 * docs/slots/stones-of-the-titan-maths.md (php artisan slot:simulate).
 */
final class StonesOfTheTitan
{
    public const CODE = 'stones-of-the-titan';

    private const SYMBOLS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'S', 'M'];

    private const NAMES = ['Caged star', 'Orrery', "Titan's shield", 'Sword in the meteorite', 'Ammonite', 'Sun geode', 'Amethyst', 'Runestone', 'Comet shard', 'Eclipse (scatter)', 'Meteor (multiplier)'];

    /** Hundredths of the base bet for 8–9, 10–11 and 12+ of a symbol. */
    private const PAYS = [
        [1000, 2500, 5000],   // A caged star
        [400, 1200, 2500],    // B orrery
        [300, 800, 1600],     // C shield
        [200, 600, 1400],     // D sword
        [150, 300, 1000],     // E ammonite
        [120, 220, 800],      // F geode
        [90, 160, 600],       // G amethyst
        [60, 120, 400],       // H runestone
        [35, 90, 250],        // I comet shard
    ];

    /** Base-game symbols (A–I, S), the same in every variant; M is set per variant. */
    private const BASE = ['A' => 4500, 'B' => 6000, 'C' => 7500, 'D' => 9000, 'E' => 11000, 'F' => 12500, 'G' => 14000, 'H' => 15000, 'I' => 16000, 'S' => 1700];

    /** The ante's scatter weight, on the base weights × 10. */
    private const ANTE_SCATTER = 21000;

    /** Free-spin symbols, the same in every variant. */
    private const FREE = ['A' => 38, 'B' => 52, 'C' => 68, 'D' => 85, 'E' => 116, 'F' => 134, 'G' => 150, 'H' => 162, 'I' => 176, 'S' => 9, 'M' => 13];

    /** Free-spin meteor values from 3× up; the weight of 2× (drawn first) is set per variant. */
    private const METEORS = [3 => 300, 4 => 220, 5 => 180, 6 => 140, 8 => 110, 10 => 85, 12 => 60, 15 => 45, 20 => 32, 25 => 22, 50 => 9, 100 => 4, 250 => 2, 500 => 1];

    /** Base-game meteor values (also in a bought spin), the same in every variant. */
    private const BASE_METEORS = [2 => 500, 3 => 300, 4 => 200, 5 => 150, 6 => 100, 8 => 70, 10 => 50, 12 => 30, 15 => 20, 20 => 12, 25 => 8, 50 => 3, 100 => 1];

    private const BUY_SCATTERS = [4 => 80, 5 => 16, 6 => 4];

    /** The Super Eclipse buy: a bought feature whose Eclipse (the tide) starts here instead of at 0. */
    private const SUPER_BUY_ECLIPSE = 10;

    /**
     * id => [rtp, base meteor weight (on BASE), weight of a 2× free-spin meteor, other caps' RTP,
     * the super buy's price in hundredths of the bet].
     *
     * The super buy's price: what it measured to be worth at the 5,000× cap, divided by the
     * variant's RTP and rounded down to whole bets (so any bet's stake is exact, and it never
     * pays below the variant's RTP). It is offered only with a cap of 5,000× or more (SlotEngine);
     * above that it pays a little more, so the caps' published RTPs stay as they are.
     * The ante tables are the base ones × 10 with ANTE_SCATTER; the buy's are the base ones
     * without the scatter.
     *
     * Other caps (hundredths of the bet => RTP): the variant's RTP plus the lowest of the three
     * modes' measured change from 5,000×, rounded down (docs/slots/trident-of-poseidon-maths.md, the same tables).
     * The buy sets the caps below 5,000×, the base game those above.
     */
    private const VARIANTS = [
        'v1-9650' => ['0.9650', 650, 610, [100_000 => '0.9169', 200_000 => '0.9441', 250_000 => '0.9511', 1_000_000 => '0.9656', 1_500_000 => '0.9657', 2_000_000 => '0.9657'], 17_900],
        'v1-9550' => ['0.9550', 634, 643, [100_000 => '0.9077', 200_000 => '0.9345', 250_000 => '0.9414', 1_000_000 => '0.9558', 1_500_000 => '0.9558', 2_000_000 => '0.9558'], 18_000],
        'v1-9450' => ['0.9450', 619, 677, [100_000 => '0.8985', 200_000 => '0.9248', 250_000 => '0.9316', 1_000_000 => '0.9458', 1_500_000 => '0.9458', 2_000_000 => '0.9458'], 18_000],
    ];

    private static ?TumbleModel $model = null;

    public static function model(): TumbleModel
    {
        return self::$model ??= self::build();
    }

    private static function build(): TumbleModel
    {
        $free = self::columns(self::weights(self::FREE));
        $baseMeteors = new WeightTable(self::BASE_METEORS);
        $buyScatters = new WeightTable(self::BUY_SCATTERS);

        $variants = [];
        foreach (self::VARIANTS as $id => [$rtp, $meteor, $twos, $rtpByCap, $superCost]) {
            $base = self::BASE + ['M' => $meteor];
            $ante = array_map(fn (int $w): int => $w * 10, $base);
            $ante['S'] = self::ANTE_SCATTER;
            $buy = $base;
            unset($buy['S']);

            $variants[$id] = new Variant(
                id: $id,
                rtp: $rtp,
                base: self::columns(self::weights($base)),
                ante: self::columns(self::weights($ante)),
                buy: self::columns(self::weights($buy)),
                free: $free,
                multipliers: new WeightTable([2 => $twos] + self::METEORS),
                buyScatters: $buyScatters,
                baseMultipliers: $baseMeteors,
                rtpByCap: $rtpByCap,
                superCost: $superCost,
            );
        }

        return new TumbleModel(
            code: self::CODE,
            name: 'Stones of the Titan',
            cols: 6,
            rows: 5,
            symbols: self::SYMBOLS,
            symbolNames: self::NAMES,
            scatter: 9,
            multiplier: 10,
            payTiers: [8, 10, 12],
            pays: self::PAYS,
            scatterPays: [4 => 400, 5 => 1000, 6 => 5000],
            freeSpinsTriggerAt: 4,
            freeSpinsAwarded: 15,
            retriggerAt: 3,
            retriggerAwarded: 5,
            maxFreeSpins: 100,
            maxWin: 500_000,
            variants: $variants,
            defaultVariant: 'v1-9650',
            baseMultipliers: true,
            freeSpinMultiplier: TumbleModel::TIDE,
            superBuyTide: self::SUPER_BUY_ECLIPSE,
        );
    }

    /**
     * Symbol letters → indices, in the model's symbol order.
     *
     * @param  array<string, int>  $byLetter
     */
    private static function weights(array $byLetter): WeightTable
    {
        $weights = [];
        foreach (self::SYMBOLS as $index => $letter) {
            if (isset($byLetter[$letter])) {
                $weights[$index] = $byLetter[$letter];
            }
        }

        return new WeightTable($weights);
    }

    /** @return list<WeightTable> the same table on all six columns */
    private static function columns(WeightTable $table): array
    {
        return array_fill(0, 6, $table);
    }
}
