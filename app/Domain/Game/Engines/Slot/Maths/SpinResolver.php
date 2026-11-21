<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use Closure;
use InvalidArgumentException;

/**
 * Plays one bet of a tumbling "pay anywhere" slot from a stream of uniform integers.
 * Pure and deterministic: the same model, variant, mode and integers always give the same
 * result. The draw order is part of the fairness contract (SlotVerifier::describe()):
 *
 *   1. The grid, column 0 to the last, each column top to bottom: one draw per cell from
 *      that column's table; a multiplier cell draws its value next (outside free spins
 *      from the variant's base multiplier table, if it has one).
 *      A bought spin (buy or super) first draws the scatter count, then their cells by a partial
 *      Fisher–Yates shuffle of 0 … cells − 1 (i-th: swap i with i + nextInt(cells − i)),
 *      then fills the other cells in order from the buy tables.
 *   2. Pay symbols with at least payTiers[0] copies anywhere win. They are removed, the
 *      rest fall, and the gaps refill: columns left to right, new cells top to bottom,
 *      drawn like step 1. Repeat until nothing wins.
 *   3. The multiplier sum (the values on the final grid) of a spin that won:
 *      - base game (models with base multipliers): multiplies the spin's line win;
 *      - free spins, SPIN: multiplies the spin's win;
 *      - free spins, TIDE: joins the tide (a running total from 0, or from the model's
 *        superBuyTide in a super buy), and the tide, if above 0, multiplies the spin's win.
 *      Scatters are counted on the final grid; their pay is never multiplied.
 *   4. A bet's total win stops at the cap, and nothing more is played.
 *
 * Cells are numbered column by column: cell = column × rows + row, row 0 at the top.
 */
final class SpinResolver
{
    /** A tumble chain or a bonus this long can't happen in practice; the cap only guarantees an end. */
    private const MAX_TUMBLES = 1000;

    /**
     * @param  Closure(int): int  $nextInt  a uniform integer in [0, n)
     * @param  int|null  $maxWin  the cap on the total win, in hundredths of the bet (default: the model's)
     */
    public static function resolve(TumbleModel $model, Variant $variant, string $mode, Closure $nextInt, bool $record = true, ?int $maxWin = null): SpinResult
    {
        if (! in_array($mode, $model->modes(), true)) {
            throw new InvalidArgumentException("Model [{$model->code}] has no mode [{$mode}].");
        }
        $cap = $maxWin ?? $model->maxWin;
        if ($cap <= 0) {
            throw new InvalidArgumentException("The max win must be positive, got {$cap}.");
        }

        $bought = $mode === 'buy' || $mode === TumbleModel::SUPER;
        $set = $variant->set($mode === 'ante' ? 'ante' : ($bought ? 'buy' : 'base'));
        $baseValues = $model->baseMultipliers ? ($variant->baseMultipliers ?? $variant->multipliers) : null;
        [$grid, $values] = $bought
            ? self::boughtGrid($model, $variant, $baseValues, $nextInt)
            : self::drawGrid($model, $set, $baseValues, $nextInt);

        $first = self::tumble($model, $set, $baseValues, $grid, $values, $nextInt, $record);
        $baseSum = $first['win'] > 0 ? $first['multiplier'] : 0;
        $lineWin = $baseSum > 0 ? $first['win'] * $baseSum : $first['win'];
        $scatterWin = $model->scatterPay($first['scatters']);
        $baseWin = min($lineWin + $scatterWin, $cap);
        $maxed = $baseWin >= $cap;
        $awarded = ! $maxed && $first['scatters'] >= $model->freeSpinsTriggerAt ? $model->freeSpinsAwarded : 0;

        $spins = [];
        if ($record) {
            $spins[] = $first['record'] + ($model->baseMultipliers ? ['multiplier' => $baseSum] : []) + [
                'scatter_win_x' => TumbleModel::x($scatterWin),
                'win_x' => TumbleModel::x($baseWin),
                'free_spins_awarded' => $awarded,
            ];
        }

        $tides = $model->freeSpinMultiplier === TumbleModel::TIDE;
        $tide = $mode === TumbleModel::SUPER ? (int) $model->superBuyTide : 0;
        $total = $baseWin;
        $freeWin = 0;
        $played = 0;
        $left = $awarded;
        while ($left > 0 && ! $maxed && $played < $model->maxFreeSpins) {
            [$grid, $values] = self::drawGrid($model, $variant->free, $variant->multipliers, $nextInt);
            $spin = self::tumble($model, $variant->free, $variant->multipliers, $grid, $values, $nextInt, $record);

            $sum = $spin['win'] > 0 ? $spin['multiplier'] : 0;
            $tide += $sum;
            $factor = $tides ? $tide : $sum;
            $win = $spin['win'] > 0 && $factor > 0 ? $spin['win'] * $factor : $spin['win'];
            if ($total + $win >= $cap) {
                $win = $cap - $total;
                $maxed = true;
            }
            $total += $win;
            $freeWin += $win;

            $retrigger = $spin['scatters'] >= $model->retriggerAt ? $model->retriggerAwarded : 0;
            $left += $retrigger - 1;
            $played++;

            if ($record) {
                $spins[] = $spin['record'] + [
                    'multiplier' => $sum,
                    'win_x' => TumbleModel::x($win),
                    'retrigger' => $retrigger,
                ] + ($tides ? ['tide' => $tide] : []);
            }
        }

        $outcome = $record ? [
            'model' => $model->code,
            'variant' => $variant->id,
            'mode' => $mode,
        ] + ($mode === TumbleModel::SUPER ? ['tide_start' => $model->superBuyTide] : []) + [
            'spins' => $spins,
            'base_win_x' => TumbleModel::x($baseWin),
            'free_spins' => $played,
            'free_spins_win_x' => TumbleModel::x($freeWin),
            'total_win_x' => TumbleModel::x($total),
            'max_win_reached' => $maxed,
        ] : null;

        return new SpinResult($total, $baseWin, $freeWin, $awarded > 0, $played, $maxed, $outcome);
    }

    /**
     * A fresh grid: every cell in order, from its column's table.
     *
     * @param  list<WeightTable>  $set
     * @param  Closure(int): int  $nextInt
     * @return array{0: list<int>, 1: list<int>} the symbols, and the multiplier values (0 elsewhere)
     */
    private static function drawGrid(TumbleModel $model, array $set, ?WeightTable $multipliers, Closure $nextInt): array
    {
        $grid = [];
        $values = [];
        for ($column = 0; $column < $model->cols; $column++) {
            $table = $set[$column];
            for ($row = 0; $row < $model->rows; $row++) {
                $symbol = $table->pick($nextInt($table->total));
                $grid[] = $symbol;
                $values[] = $symbol === $model->multiplier ? self::multiplierValue($model, $multipliers, $nextInt) : 0;
            }
        }

        return [$grid, $values];
    }

    /**
     * A bought trigger spin: the scatters first, then the rest from the buy tables (a
     * multiplier cell draws its value next).
     *
     * @param  Closure(int): int  $nextInt
     * @return array{0: list<int>, 1: list<int>}
     */
    private static function boughtGrid(TumbleModel $model, Variant $variant, ?WeightTable $multipliers, Closure $nextInt): array
    {
        $count = $variant->buyScatters->pick($nextInt($variant->buyScatters->total));
        $cells = range(0, $model->cells - 1);
        for ($i = 0; $i < $count; $i++) {
            $j = $i + $nextInt($model->cells - $i);
            [$cells[$i], $cells[$j]] = [$cells[$j], $cells[$i]];
        }
        $scatters = array_flip(array_slice($cells, 0, $count));

        $grid = [];
        $values = [];
        for ($cell = 0; $cell < $model->cells; $cell++) {
            if (isset($scatters[$cell])) {
                $grid[] = $model->scatter;
                $values[] = 0;
            } else {
                $table = $variant->buy[intdiv($cell, $model->rows)];
                $symbol = $table->pick($nextInt($table->total));
                $grid[] = $symbol;
                $values[] = $symbol === $model->multiplier ? self::multiplierValue($model, $multipliers, $nextInt) : 0;
            }
        }

        return [$grid, $values];
    }

    /**
     * Pays, removes and refills until nothing wins.
     *
     * @param  list<WeightTable>  $set
     * @param  list<int>  $grid
     * @param  list<int>  $values
     * @param  Closure(int): int  $nextInt
     * @return array{win: int, scatters: int, multiplier: int, record: array<string, mixed>}
     */
    private static function tumble(TumbleModel $model, array $set, ?WeightTable $multipliers, array $grid, array $values, Closure $nextInt, bool $record): array
    {
        $rows = $model->rows;
        $minimum = $model->payTiers[0];
        $result = $record ? ['grid' => self::encode($model, $grid), 'mults' => self::multiplierValues($model, $grid, $values), 'tumbles' => []] : [];
        $win = 0;

        for ($step = 0; $step <= self::MAX_TUMBLES; $step++) {
            $counts = array_fill(0, $model->symbolCount, 0);
            foreach ($grid as $symbol) {
                $counts[$symbol]++;
            }

            $winning = [];
            $wins = [];
            for ($symbol = 0; $symbol < $model->payCount; $symbol++) {
                if ($counts[$symbol] >= $minimum) {
                    $pay = $model->pay($symbol, $counts[$symbol]);
                    $win += $pay;
                    $winning[$symbol] = true;
                    if ($record) {
                        $wins[] = ['symbol' => $model->symbols[$symbol], 'count' => $counts[$symbol], 'win_x' => TumbleModel::x($pay)];
                    }
                }
            }

            if ($winning === [] || $step === self::MAX_TUMBLES) {
                $sum = 0;
                foreach ($values as $value) {
                    $sum += $value;
                }

                return ['win' => $win, 'scatters' => $counts[$model->scatter], 'multiplier' => $sum, 'record' => $result];
            }

            $nextGrid = [];
            $nextValues = [];
            $fill = [];
            $fillMults = [];
            for ($column = 0; $column < $model->cols; $column++) {
                $kept = [];
                $keptValues = [];
                for ($row = 0, $cell = $column * $rows; $row < $rows; $row++, $cell++) {
                    if (! isset($winning[$grid[$cell]])) {
                        $kept[] = $grid[$cell];
                        $keptValues[] = $values[$cell];
                    }
                }

                $table = $set[$column];
                $added = '';
                for ($i = count($kept); $i < $rows; $i++) {
                    $symbol = $table->pick($nextInt($table->total));
                    $value = $symbol === $model->multiplier ? self::multiplierValue($model, $multipliers, $nextInt) : 0;
                    $nextGrid[] = $symbol;
                    $nextValues[] = $value;
                    if ($record) {
                        $added .= $model->symbols[$symbol];
                        if ($value > 0) {
                            $fillMults[] = $value;
                        }
                    }
                }
                foreach ($kept as $i => $symbol) {
                    $nextGrid[] = $symbol;
                    $nextValues[] = $keptValues[$i];
                }
                $fill[] = $added;
            }

            $grid = $nextGrid;
            $values = $nextValues;
            if ($record) {
                $result['tumbles'][] = ['wins' => $wins, 'fill' => $fill, 'fill_mults' => $fillMults];
            }
        }

        throw new \LogicException('unreachable');
    }

    /** @param  Closure(int): int  $nextInt */
    private static function multiplierValue(TumbleModel $model, ?WeightTable $multipliers, Closure $nextInt): int
    {
        if ($multipliers === null) {
            throw new InvalidArgumentException("Model [{$model->code}]: a multiplier landed in a reel set that has no multiplier values.");
        }

        return $multipliers->pick($nextInt($multipliers->total));
    }

    /** @param  list<int>  $grid */
    private static function encode(TumbleModel $model, array $grid): string
    {
        $out = '';
        foreach ($grid as $symbol) {
            $out .= $model->symbols[$symbol];
        }

        return $out;
    }

    /**
     * The multiplier values on a grid, in cell order.
     *
     * @param  list<int>  $grid
     * @param  list<int>  $values
     * @return list<int>
     */
    private static function multiplierValues(TumbleModel $model, array $grid, array $values): array
    {
        $out = [];
        foreach ($grid as $cell => $symbol) {
            if ($symbol === $model->multiplier) {
                $out[] = $values[$cell];
            }
        }

        return $out;
    }
}
