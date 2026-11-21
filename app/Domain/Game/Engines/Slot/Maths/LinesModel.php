<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use InvalidArgumentException;

/**
 * The rules and paytable of a paylines slot: fixed lines across a few rows of reels, paying
 * left to right from the first reel. Amounts are integers in hundredths of the bet (the
 * whole stake, all lines), so the maths never touches floats; the paytable is listed in
 * multiples of the line bet (bet ÷ lines), as players read it.
 *
 * Symbols are indices into $symbols: the pay symbols first (0 … payCount − 1), then the
 * scatter, then the wild. A wild stands in for every pay symbol and carries a value: 1 for a
 * plain wild, or a multiplier of 2 or more. A line's multiplier is the sum of the multipliers
 * among its counted cells (1 when it has none). In free spins every wild that lands also adds
 * 1 to "the amore", a running multiplier that starts at 1 and multiplies each free spin's line
 * wins from then on. Scatters pay anywhere, are never multiplied, and trigger the free spins.
 *
 * Lines models reuse Variant: base, buy and free are the reels' tables ("ante" repeats base:
 * lines models have no ante); baseMultipliers draws a wild's value outside free spins and
 * multipliers in them; buyScatters is how many scatters a bought spin places.
 */
final readonly class LinesModel
{
    /** Mode => what the stake buys, in hundredths of the bet. */
    public const COSTS = ['base' => 100, 'buy' => 10_000];

    public int $cells;

    public int $payCount;

    public int $symbolCount;

    /** Lines models have no super buy; the property keeps the engine's checks uniform. */
    public ?int $superBuyTide;

    /**
     * @param  list<string>  $symbols  one character each, pay symbols first, then scatter, then wild
     * @param  list<string>  $symbolNames  for the rules screen and describe()
     * @param  list<list<int>>  $lines  per line, the row it crosses on each reel (row 0 at the top)
     * @param  list<array<int, int>>  $pays  per pay symbol, count => pay in multiples of the line bet
     * @param  array<int, int>  $scatterPays  scatter count => pay in hundredths of the bet (the highest count <= the scatters on screen applies)
     * @param  array<int, int>  $freeSpinsAwarded  scatter count => free spins (the highest count <= the scatters on screen applies)
     * @param  array<string, Variant>  $variants  id => variant
     * @param  int  $maxWin  the default cap on a bet's total win; a game may pick another its variant has measured (Variant::$rtpByCap)
     */
    public function __construct(
        public string $code,
        public string $name,
        public int $cols,
        public int $rows,
        public array $symbols,
        public array $symbolNames,
        public int $scatter,
        public int $wild,
        public array $lines,
        public array $pays,
        public array $scatterPays,
        public array $freeSpinsAwarded,
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
        $this->superBuyTide = null;

        if ($scatter !== $this->payCount || $wild !== $this->payCount + 1 || $this->symbolCount !== $this->payCount + 2) {
            throw new InvalidArgumentException("Model [{$code}]: list the pay symbols first, then the scatter, then the wild.");
        }
        foreach ($lines as $i => $line) {
            if (count($line) !== $cols || array_filter($line, fn (int $row): bool => $row < 0 || $row >= $rows) !== []) {
                throw new InvalidArgumentException("Model [{$code}]: line ".($i + 1).' needs one row (0–'.($rows - 1).') per reel.');
            }
        }
        if (count(array_unique(array_map(fn (array $line): string => implode(',', $line), $lines))) !== count($lines)) {
            throw new InvalidArgumentException("Model [{$code}]: two lines cross the same rows.");
        }
        foreach ($pays as $symbol => $byCount) {
            foreach ($byCount as $count => $pay) {
                if ($count < 2 || $count > $cols || $pay <= 0 || ($pay * 100) % count($lines) !== 0) {
                    throw new InvalidArgumentException("Model [{$code}]: symbol {$symbol} pays {$pay} for {$count}; a pay needs a count from 2 to {$cols} and a whole number of hundredths of the bet.");
                }
            }
        }
        if (! isset($variants[$defaultVariant])) {
            throw new InvalidArgumentException("Model [{$code}]: the default variant [{$defaultVariant}] doesn't exist.");
        }
        foreach ($variants as $id => $variant) {
            foreach (['base', 'buy', 'free'] as $set) {
                $tables = $variant->set($set);
                if (count($tables) !== $cols) {
                    throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: [{$set}] needs one table per reel.");
                }
                // A line starts on the first reel, so a wild there could make a line of wilds alone.
                if (in_array($wild, $tables[0]->values, true)) {
                    throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: no wild on the first reel, but [{$set}] has one.");
                }
            }
            if (array_filter($variant->buy, fn (WeightTable $table): bool => in_array($scatter, $table->values, true)) !== []) {
                throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: a bought spin places its scatters itself; [buy] can't hold one.");
            }
            foreach ([$variant->multipliers, $variant->baseMultipliers] as $values) {
                if ($values === null || array_filter($values->values, fn (int $value): bool => $value < 1) !== []) {
                    throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: wild values are 1 (plain) or a multiplier, inside and outside free spins.");
                }
            }
            if (array_filter($variant->buyScatters->values, fn (int $count): bool => $count < min(array_keys($freeSpinsAwarded)) || $count > $this->cells) !== []) {
                throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: a bought spin must place enough scatters to trigger the free spins.");
            }
            if ($variant->superCost !== null) {
                throw new InvalidArgumentException("Model [{$code}] variant [{$id}]: lines models have no super buy.");
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
        return array_keys(self::COSTS);
    }

    /** What a mode's stake buys with a variant, in hundredths of the bet. */
    public function cost(string $mode, Variant $variant): int
    {
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
            ?? throw new InvalidArgumentException("Model [{$this->code}] variant [{$variant->id}] has no measured RTP for a max win of ".TumbleModel::x($maxWin).'×.');
    }

    /** What a line of $count pay symbol $symbol pays, in hundredths of the bet (0 when that count doesn't pay). */
    public function linePay(int $symbol, int $count): int
    {
        $pay = $this->pays[$symbol][$count] ?? 0;

        return intdiv($pay * 100, count($this->lines));
    }

    /** The scatter pay for $count scatters, in hundredths of the bet. */
    public function scatterPay(int $count): int
    {
        return self::tier($this->scatterPays, $count);
    }

    /** The free spins $count scatters award (0 below the first tier). */
    public function freeSpinsFor(int $count): int
    {
        return self::tier($this->freeSpinsAwarded, $count);
    }

    public function variant(string $id): Variant
    {
        return $this->variants[$id] ?? throw new InvalidArgumentException("Model [{$this->code}] has no variant [{$id}].");
    }

    /** @param  array<int, int>  $tiers  from => value, ascending */
    private static function tier(array $tiers, int $count): int
    {
        $value = 0;
        foreach ($tiers as $from => $amount) {
            if ($count >= $from) {
                $value = $amount;
            }
        }

        return $value;
    }
}
