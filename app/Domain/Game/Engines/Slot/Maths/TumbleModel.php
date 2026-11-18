<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use InvalidArgumentException;

/**
 * The rules and paytable of a "pay anywhere" tumbling slot. Amounts are integers in
 * hundredths of the base bet (800 = 8.00×), so the maths never touches floats.
 *
 * Symbols are indices into $symbols: the pay symbols first (0 … payCount − 1), then the
 * scatter, then the multiplier. A model's paytable and rules never change once live;
 * a change is a new model code. Its weight tables live in its variants.
 */
final readonly class TumbleModel
{
    /** Mode => what the stake buys, in hundredths of the base bet. */
    public const COSTS = ['base' => 100, 'ante' => 125, 'buy' => 10_000];

    public int $cells;

    public int $payCount;

    public int $symbolCount;

    /**
     * @param  list<string>  $symbols  one character each, pay symbols first, then scatter, then multiplier
     * @param  list<string>  $symbolNames  for the rules screen and describe()
     * @param  list<int>  $payTiers  the counts each pay tier starts at, ascending (e.g. 8, 10, 12)
     * @param  list<list<int>>  $pays  per pay symbol, its pay for each tier
     * @param  array<int, int>  $scatterPays  scatter count => pay (the highest count <= the scatters on screen applies)
     * @param  array<string, Variant>  $variants  id => variant
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $cols,
        public int $rows,
        public array $symbols,
        public array $symbolNames,
        public int $scatter,
        public int $multiplier,
        public array $payTiers,
        public array $pays,
        public array $scatterPays,
        public int $freeSpinsTriggerAt,
        public int $freeSpinsAwarded,
        public int $retriggerAt,
        public int $retriggerAwarded,
        public int $maxFreeSpins,
        public int $maxWin,
        public array $variants,
        public string $defaultVariant,
    ) {
        $this->cells = $cols * $rows;
        $this->payCount = count($pays);
        $this->symbolCount = count($symbols);

        if ($scatter !== $this->payCount || $multiplier !== $this->payCount + 1 || $this->symbolCount !== $this->payCount + 2) {
            throw new InvalidArgumentException("Model [{$code}]: list the pay symbols first, then the scatter, then the multiplier.");
        }
        foreach ($pays as $symbol => $tiers) {
            if (count($tiers) !== count($payTiers)) {
                throw new InvalidArgumentException("Model [{$code}]: symbol {$symbol} needs a pay for every tier.");
            }
        }
        if (! isset($variants[$defaultVariant])) {
            throw new InvalidArgumentException("Model [{$code}]: the default variant [{$defaultVariant}] doesn't exist.");
        }
    }

    /** What $count of pay symbol $symbol pays (0 below the first tier). */
    public function pay(int $symbol, int $count): int
    {
        $pay = 0;
        foreach ($this->payTiers as $tier => $from) {
            if ($count >= $from) {
                $pay = $this->pays[$symbol][$tier];
            }
        }

        return $pay;
    }

    public function scatterPay(int $count): int
    {
        $pay = 0;
        foreach ($this->scatterPays as $from => $amount) {
            if ($count >= $from) {
                $pay = $amount;
            }
        }

        return $pay;
    }

    public function variant(string $id): Variant
    {
        return $this->variants[$id] ?? throw new InvalidArgumentException("Model [{$this->code}] has no variant [{$id}].");
    }

    /** Hundredths of the base bet, as a 2-dp decimal string (1250 → "12.50"). */
    public static function x(int $hundredths): string
    {
        return sprintf('%d.%02d', intdiv($hundredths, 100), $hundredths % 100);
    }
}
