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
 *
 * Multipliers: with $baseMultipliers they land in the base game too, and multiply the line
 * win of the spin they end on. In free spins, SPIN multiplies a winning spin by its own sum;
 * TIDE adds the sum of every winning spin to a running total that multiplies each winning
 * spin from then on.
 *
 * A super buy (models with $superBuyTide) is a bought feature whose tide starts at that value
 * instead of 0; each variant sets its price (Variant::$superCost).
 */
final readonly class TumbleModel
{
    /** Mode => what the stake buys, in hundredths of the base bet. A super buy's price is its variant's. */
    public const COSTS = ['base' => 100, 'ante' => 125, 'buy' => 10_000];

    /** The super buy's mode: a bought feature that starts with the tide at $superBuyTide. */
    public const SUPER = 'super';

    /** Free spins: each winning spin is multiplied by the sum on its own grid. */
    public const SPIN = 'spin';

    /** Free spins: each winning spin's sum joins a running total that multiplies it and every later winning spin. */
    public const TIDE = 'tide';

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
     * @param  int  $maxWin  the default cap on a bet's total win; a game may pick another its variant has measured (Variant::$rtpByCap)
     * @param  bool  $baseMultipliers  whether multipliers land outside free spins
     * @param  string  $freeSpinMultiplier  SPIN or TIDE
     * @param  int|null  $superBuyTide  the tide a super buy starts with (TIDE models only; null: no super buy)
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
        public bool $baseMultipliers = false,
        public string $freeSpinMultiplier = self::SPIN,
        public ?int $superBuyTide = null,
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
        if (! in_array($freeSpinMultiplier, [self::SPIN, self::TIDE], true)) {
            throw new InvalidArgumentException("Model [{$code}]: free-spin multipliers are SPIN or TIDE, not [{$freeSpinMultiplier}].");
        }
        if ($superBuyTide !== null && ($freeSpinMultiplier !== self::TIDE || $superBuyTide <= 0)) {
            throw new InvalidArgumentException("Model [{$code}]: a super buy starts the tide above 0, so it needs TIDE free spins.");
        }
        foreach ($variants as $id => $variant) {
            if (($superBuyTide !== null) !== ($variant->superCost !== null) || ($variant->superCost !== null && $variant->superCost <= 0)) {
                throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: give every variant a super buy price exactly when the model has a super buy.");
            }
            foreach (['base', 'ante', 'buy'] as $set) {
                foreach ($variant->set($set) as $table) {
                    if (! $baseMultipliers && in_array($multiplier, $table->values, true)) {
                        throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: multipliers only land in free spins, but [{$set}] has one.");
                    }
                }
            }
            if (isset($variant->rtpByCap[$maxWin])) {
                throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: the default cap's RTP is the variant's own; don't list it again.");
            }
        }
    }

    /**
     * The modes a bet may be played in.
     *
     * @return list<string>
     */
    public function modes(): array
    {
        return $this->superBuyTide === null ? array_keys(self::COSTS) : [...array_keys(self::COSTS), self::SUPER];
    }

    /** What a mode's stake buys with a variant, in hundredths of the base bet. */
    public function cost(string $mode, Variant $variant): int
    {
        if ($mode === self::SUPER && $this->superBuyTide !== null) {
            return (int) $variant->superCost;
        }

        return self::COSTS[$mode] ?? throw new InvalidArgumentException("Model [{$this->code}] has no mode [{$mode}].");
    }

    /**
     * The caps a game may pick with this variant, each with the RTP measured at it.
     *
     * @return array<int, string> cap (hundredths of the bet) => rtp, ascending
     */
    public function maxWinChoices(Variant $variant): array
    {
        $choices = [$this->maxWin => $variant->rtp] + $variant->rtpByCap;
        ksort($choices);

        return $choices;
    }

    /** The RTP of a variant played with a cap. */
    public function rtp(Variant $variant, int $maxWin): string
    {
        return $this->maxWinChoices($variant)[$maxWin]
            ?? throw new InvalidArgumentException("Model [{$this->code}] variant [{$variant->id}] has no measured RTP for a max win of ".self::x($maxWin).'×.');
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
