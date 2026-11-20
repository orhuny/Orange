<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\TumbleModel;
use App\Domain\Game\Engines\Slot\Maths\Variant;
use App\Domain\Game\Engines\Slot\Maths\WeightTable;
use App\Domain\Game\Engines\Slot\Models\SugarOrchard;

/**
 * A stream that lands each draw on a chosen symbol or value, so a test can lay out exact
 * grids. Table draws are recognised by their total; anything else (the buy shuffle) takes
 * the queued integer as is. Once the queue is empty it deals a grid that never wins:
 * A, B, … I in turn, at most four of each.
 *
 * @param  list<string|int|null>  $queue  letters for symbol draws, values for multiplier and scatter-count draws, indices for the shuffle; null deals a filler symbol
 * @param  TumbleModel|null  $model  default: Sugar Orchard
 */
function scripted(Variant $variant, array $queue, ?TumbleModel $model = null): Closure
{
    $model ??= SugarOrchard::model();
    $index = array_flip($model->symbols);
    $starts = function (WeightTable $table): array {
        $at = [];
        $sum = 0;
        foreach ($table->weights() as $value => $weight) {
            $at[$value] = $sum;
            $sum += $weight;
        }

        return $at;
    };
    $symbolTables = [];
    foreach (['base', 'ante', 'buy', 'free'] as $set) {
        $symbolTables[$variant->set($set)[0]->total] = $starts($variant->set($set)[0]);
    }
    $multipliers = [$variant->multipliers->total => $starts($variant->multipliers)];
    if ($variant->baseMultipliers !== null) {
        $multipliers[$variant->baseMultipliers->total] = $starts($variant->baseMultipliers);
    }
    $scatterCounts = [$variant->buyScatters->total => $starts($variant->buyScatters)];
    $filler = 0;

    return function (int $n) use (&$queue, &$filler, $index, $symbolTables, $multipliers, $scatterCounts): int {
        $item = array_shift($queue);
        if (isset($symbolTables[$n])) {
            $letter = $item ?? 'ABCDEFGHI'[$filler++ % 9];

            return $symbolTables[$n][$index[$letter]];
        }
        if (isset($multipliers[$n])) {
            return $multipliers[$n][$item ?? 2];
        }
        if (isset($scatterCounts[$n])) {
            return $scatterCounts[$n][$item];
        }

        return (int) $item;
    };
}
