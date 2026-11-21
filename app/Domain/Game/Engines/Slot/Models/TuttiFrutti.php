<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Models;

use App\Domain\Game\Engines\Slot\Maths\LinesModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;

/**
 * Tutti Frutti: our first paylines slot. 5 reels × 3 rows, 20 fixed lines paying left to
 * right; the kiss (W, reels 2–5) is wild, plain or carrying 2×, 3× or 5× (a line's
 * multipliers add up); the cocktail (S) scatters: 3, 4 or 5 pay 2×, 10× or 50× the bet and
 * award 10, 12 or 15 free spins (3+ in free spins add 5). In free spins every kiss that lands
 * adds 1 to the amore, which multiplies every free spin's line wins. Bonus buy 100×, no ante.
 * Max win 5,000× by default; a game may pick another measured cap (Variant::$rtpByCap).
 *
 * LIVE TABLES ARE IMMUTABLE. A bet's params name its variant and verification replays it,
 * so never edit a variant once a real bet has used it: add a new id (v2-…) instead.
 * The measured RTP of every variant, mode and cap is in docs/slots/tutti-frutti-maths.md
 * (php artisan slot:simulate tutti-frutti).
 */
final class TuttiFrutti
{
    public const CODE = 'tutti-frutti';

    private const SYMBOLS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'S', 'W'];

    private const NAMES = ['Peach', 'Pineapple', 'Dragon fruit', 'Pomegranate', 'Mango', 'Lime', 'Blueberries', 'Fig', 'Lychee', 'Cocktail (scatter)', 'Kiss (wild)'];

    /** Per line, the row (0 at the top) it crosses on each reel. */
    private const LINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [0, 0, 1, 2, 2], [2, 2, 1, 0, 0], [1, 0, 0, 0, 1], [1, 2, 2, 2, 1], [0, 1, 1, 1, 0],
        [2, 1, 1, 1, 2], [1, 0, 1, 2, 1], [1, 2, 1, 0, 1], [0, 1, 0, 1, 0], [2, 1, 2, 1, 2],
        [1, 1, 0, 1, 1], [1, 1, 2, 1, 1], [0, 0, 2, 0, 0], [2, 2, 0, 2, 2], [0, 2, 2, 2, 0],
    ];

    /** Multiples of the line bet (the bet ÷ 20) for 2–5 on a line. */
    private const PAYS = [
        [2 => 5, 3 => 50, 4 => 200, 5 => 1000],   // A peach
        [3 => 40, 4 => 150, 5 => 500],            // B pineapple
        [3 => 30, 4 => 100, 5 => 400],            // C dragon fruit
        [3 => 25, 4 => 80, 5 => 300],             // D pomegranate
        [3 => 15, 4 => 40, 5 => 150],             // E mango
        [3 => 10, 4 => 30, 5 => 120],             // F lime
        [3 => 10, 4 => 25, 5 => 100],             // G blueberries
        [3 => 5, 4 => 20, 5 => 80],               // H fig
        [3 => 5, 4 => 15, 5 => 60],               // I lychee
    ];

    /** Hundredths of the bet for 3, 4 and 5+ scatters anywhere. */
    private const SCATTER_PAYS = [3 => 200, 4 => 1_000, 5 => 5_000];

    private const FREE_SPINS = [3 => 10, 4 => 12, 5 => 15];

    /** The fruit on every reel, the same in every set: A–I. */
    private const FRUIT = [400, 500, 600, 700, 1100, 1200, 1300, 1400, 1500];

    /** Free spins: the scatter and the kiss on reels 2–5, the same in every variant. */
    private const FREE_SCATTER = 250;

    private const FREE_KISS = 635;

    /** A kiss's value: 1 plain, else its multiplier. */
    private const BASE_KISSES = [1 => 80, 2 => 12, 3 => 6, 5 => 2];

    private const FREE_KISSES = [1 => 70, 2 => 16, 3 => 9, 5 => 5];

    /**
     * id => [rtp, base scatter, base kiss (reels 2–5), bought scatters 3/4/5, rtp by other cap].
     * The buy tables are the base ones without the scatter. The RTP of another cap is the variant's
     * plus the lower of the base and buy modes' measured change from 5,000×, rounded down
     * (docs/slots/tutti-frutti-maths.md).
     */
    private const VARIANTS = [
        'v1-9650' => ['0.9650', 215, 430, [3 => 561, 4 => 329, 5 => 110],
            [100_000 => '0.9430', 200_000 => '0.9590', 250_000 => '0.9614', 1_000_000 => '0.9651', 1_500_000 => '0.9651', 2_000_000 => '0.9651']],
        'v1-9550' => ['0.9550', 215, 421, [3 => 573, 4 => 324, 5 => 103],
            [100_000 => '0.9332', 200_000 => '0.9490', 250_000 => '0.9515', 1_000_000 => '0.9552', 1_500_000 => '0.9552', 2_000_000 => '0.9552']],
        'v1-9450' => ['0.9450', 215, 411, [3 => 586, 4 => 317, 5 => 97],
            [100_000 => '0.9236', 200_000 => '0.9391', 250_000 => '0.9415', 1_000_000 => '0.9452', 1_500_000 => '0.9452', 2_000_000 => '0.9452']],
    ];

    private static ?LinesModel $model = null;

    public static function model(): LinesModel
    {
        return self::$model ??= self::build();
    }

    private static function build(): LinesModel
    {
        $free = self::reels(self::FREE_SCATTER, self::FREE_KISS);

        $variants = [];
        foreach (self::VARIANTS as $id => [$rtp, $scatter, $kiss, $bought, $rtpByCap]) {
            $base = self::reels($scatter, $kiss);
            $variants[$id] = new Variant(
                id: $id,
                rtp: $rtp,
                base: $base,
                // Lines models have no ante: the base tables again.
                ante: $base,
                buy: self::reels(0, $kiss),
                free: $free,
                multipliers: new WeightTable(self::FREE_KISSES),
                buyScatters: new WeightTable($bought),
                baseMultipliers: new WeightTable(self::BASE_KISSES),
                rtpByCap: $rtpByCap,
            );
        }

        return new LinesModel(
            code: self::CODE,
            name: 'Tutti Frutti',
            cols: 5,
            rows: 3,
            symbols: self::SYMBOLS,
            symbolNames: self::NAMES,
            scatter: 9,
            wild: 10,
            lines: self::LINES,
            pays: self::PAYS,
            scatterPays: self::SCATTER_PAYS,
            freeSpinsAwarded: self::FREE_SPINS,
            retriggerAt: 3,
            retriggerAwarded: 5,
            maxFreeSpins: 100,
            maxWin: 500_000,
            variants: $variants,
            defaultVariant: 'v1-9650',
        );
    }

    /**
     * One table per reel: the fruit, then the scatter (when it has one), then the kiss on
     * reels 2–5. Symbols keep the model's order, so the draw order is A … I, S, W.
     *
     * @return list<WeightTable>
     */
    private static function reels(int $scatter, int $kiss): array
    {
        $reels = [];
        for ($reel = 0; $reel < 5; $reel++) {
            $weights = self::FRUIT;
            if ($scatter > 0) {
                $weights[9] = $scatter;
            }
            if ($reel > 0) {
                $weights[10] = $kiss;
            }
            $reels[] = new WeightTable($weights);
        }

        return $reels;
    }
}
