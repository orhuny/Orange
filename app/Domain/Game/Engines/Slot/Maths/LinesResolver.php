<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use Closure;
use InvalidArgumentException;

/**
 * Plays one bet of a paylines slot from a stream of uniform integers. Pure and deterministic:
 * the same model, variant, mode and integers always give the same result. The draw order is
 * part of the fairness contract (SlotVerifier::describe()):
 *
 *   1. The screen, reel 0 to the last, each reel top to bottom: one draw per cell from that
 *      reel's table; a wild draws its value next (outside free spins from "base_wilds", in
 *      them from "free_wilds"). A bought spin first draws its scatter count, then their cells
 *      by a partial Fisher–Yates shuffle of 0 … cells − 1 (i-th: swap i with
 *      i + nextInt(cells − i)), then fills the other cells in order from the buy tables.
 *   2. Every line, in order: the symbol on reel 0 and the run of that symbol or wilds from
 *      reel 0 rightwards. A run the paytable lists pays, times the sum of the multipliers of
 *      the wilds in it (1 when there are none).
 *   3. Scatters anywhere pay the scatter pay (never multiplied) and award free spins.
 *   4. Free spins: the amore starts at 1; every wild that lands adds 1, before the spin pays;
 *      a free spin's line wins are multiplied by the amore. Scatters retrigger.
 *   5. A bet's total win stops at the cap, and nothing more is played.
 *
 * Cells are numbered reel by reel: cell = reel × rows + row, row 0 at the top.
 */
final class LinesResolver
{
    /** @var array<string, list<list<int>>> model code => each line's cells */
    private static array $lineCells = [];

    /**
     * @param  Closure(int): int  $nextInt  a uniform integer in [0, n)
     * @param  int|null  $maxWin  the cap on the total win, in hundredths of the bet (default: the model's)
     */
    public static function resolve(LinesModel $model, Variant $variant, string $mode, Closure $nextInt, bool $record = true, ?int $maxWin = null): SpinResult
    {
        if (! in_array($mode, $model->modes(), true)) {
            throw new InvalidArgumentException("Model [{$model->code}] has no mode [{$mode}].");
        }
        $cap = $maxWin ?? $model->maxWin;
        if ($cap <= 0) {
            throw new InvalidArgumentException("The max win must be positive, got {$cap}.");
        }
        $lines = self::$lineCells[$model->code] ??= self::cells($model);
        $baseWilds = $variant->baseMultipliers ?? $variant->multipliers;

        [$grid, $values] = $mode === 'buy'
            ? self::boughtGrid($model, $variant, $baseWilds, $nextInt)
            : self::drawGrid($model, $variant->base, $baseWilds, $nextInt);
        $first = self::pay($model, $lines, $grid, $values, $record);
        $scatterWin = $model->scatterPay($first['scatters']);
        $baseWin = min($first['win'] + $scatterWin, $cap);
        $maxed = $baseWin >= $cap;
        $awarded = $maxed ? 0 : $model->freeSpinsFor($first['scatters']);

        $spins = [];
        if ($record) {
            $spins[] = $first['record'] + [
                'scatter_win_x' => TumbleModel::x($scatterWin),
                'win_x' => TumbleModel::x($baseWin),
                'free_spins_awarded' => $awarded,
            ];
        }

        $amore = 1;
        $total = $baseWin;
        $freeWin = 0;
        $played = 0;
        $left = $awarded;
        while ($left > 0 && ! $maxed && $played < $model->maxFreeSpins) {
            [$grid, $values] = self::drawGrid($model, $variant->free, $variant->multipliers, $nextInt);
            $spin = self::pay($model, $lines, $grid, $values, $record);
            $amore += $spin['wilds'];

            $scatterPay = $model->scatterPay($spin['scatters']);
            $win = $spin['win'] * $amore + $scatterPay;
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
                    'amore' => $amore,
                    'scatter_win_x' => TumbleModel::x($scatterPay),
                    'win_x' => TumbleModel::x($win),
                    'retrigger' => $retrigger,
                ];
            }
        }

        $outcome = $record ? [
            'model' => $model->code,
            'variant' => $variant->id,
            'mode' => $mode,
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
     * Pays every line of a screen.
     *
     * @param  list<list<int>>  $lines
     * @param  list<int>  $grid
     * @param  list<int>  $values  a wild's value, 0 elsewhere
     * @return array{win: int, scatters: int, wilds: int, record: array<string, mixed>}
     */
    private static function pay(LinesModel $model, array $lines, array $grid, array $values, bool $record): array
    {
        $wild = $model->wild;
        $scatter = $model->scatter;
        $win = 0;
        $wins = [];

        foreach ($lines as $i => $cells) {
            $symbol = $grid[$cells[0]];
            if ($symbol >= $model->payCount) {
                continue;
            }
            $count = 1;
            $multiplier = 0;
            for ($reel = 1, $reels = count($cells); $reel < $reels; $reel++) {
                $on = $grid[$cells[$reel]];
                if ($on === $symbol) {
                    $count++;
                } elseif ($on === $wild) {
                    $count++;
                    $value = $values[$cells[$reel]];
                    if ($value > 1) {
                        $multiplier += $value;
                    }
                } else {
                    break;
                }
            }
            $pay = $model->linePay($symbol, $count);
            if ($pay === 0) {
                continue;
            }
            $multiplier = max(1, $multiplier);
            $win += $pay * $multiplier;
            if ($record) {
                $wins[] = ['line' => $i + 1, 'symbol' => $model->symbols[$symbol], 'count' => $count, 'multiplier' => $multiplier, 'win_x' => TumbleModel::x($pay * $multiplier)];
            }
        }

        $scatters = 0;
        $wilds = 0;
        foreach ($grid as $symbol) {
            if ($symbol === $scatter) {
                $scatters++;
            } elseif ($symbol === $wild) {
                $wilds++;
            }
        }

        $result = [];
        if ($record) {
            $letters = '';
            $wildValues = [];
            foreach ($grid as $cell => $symbol) {
                $letters .= $model->symbols[$symbol];
                if ($symbol === $wild) {
                    $wildValues[] = $values[$cell];
                }
            }
            $result = ['grid' => $letters, 'wilds' => $wildValues, 'lines' => $wins, 'line_win_x' => TumbleModel::x($win), 'scatters' => $scatters];
        }

        return ['win' => $win, 'scatters' => $scatters, 'wilds' => $wilds, 'record' => $result];
    }

    /**
     * A fresh screen: every cell in order, from its reel's table.
     *
     * @param  list<WeightTable>  $set
     * @param  Closure(int): int  $nextInt
     * @return array{0: list<int>, 1: list<int>} the symbols, and the wild values (0 elsewhere)
     */
    private static function drawGrid(LinesModel $model, array $set, WeightTable $wilds, Closure $nextInt): array
    {
        $grid = [];
        $values = [];
        for ($reel = 0; $reel < $model->cols; $reel++) {
            $table = $set[$reel];
            for ($row = 0; $row < $model->rows; $row++) {
                $symbol = $table->pick($nextInt($table->total));
                $grid[] = $symbol;
                $values[] = $symbol === $model->wild ? $wilds->pick($nextInt($wilds->total)) : 0;
            }
        }

        return [$grid, $values];
    }

    /**
     * A bought spin: the scatters first, then the rest from the buy tables (a wild draws its
     * value next).
     *
     * @param  Closure(int): int  $nextInt
     * @return array{0: list<int>, 1: list<int>}
     */
    private static function boughtGrid(LinesModel $model, Variant $variant, WeightTable $wilds, Closure $nextInt): array
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
                $values[] = $symbol === $model->wild ? $wilds->pick($nextInt($wilds->total)) : 0;
            }
        }

        return [$grid, $values];
    }

    /**
     * Each line's cells, reel by reel.
     *
     * @return list<list<int>>
     */
    private static function cells(LinesModel $model): array
    {
        return array_map(
            fn (array $rows): array => array_map(fn (int $reel, int $row): int => $reel * $model->rows + $row, array_keys($rows), $rows),
            $model->lines,
        );
    }
}
