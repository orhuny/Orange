<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Slot\Maths\WeightTable;

it('lands each r on the first entry whose running total exceeds it', function (): void {
    $table = new WeightTable([7 => 2, 3 => 1, 9 => 3]);

    expect($table->total)->toBe(6)
        ->and(array_map(fn (int $r): int => $table->pick($r), range(0, 5)))->toBe([7, 7, 3, 9, 9, 9])
        ->and($table->weights())->toBe([7 => 2, 3 => 1, 9 => 3]);
});

it('draws with one call to nextInt(total)', function (): void {
    $asked = [];
    $value = (new WeightTable([1 => 5, 2 => 5]))->draw(function (int $n) use (&$asked): int {
        $asked[] = $n;

        return 7;
    });

    expect($asked)->toBe([10])->and($value)->toBe(2);
});

it('refuses empty tables, non-positive weights and an r past the total', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'empty' => [fn () => new WeightTable([])],
    'zero weight' => [fn () => new WeightTable([1 => 0])],
    'r too big' => [fn () => (new WeightTable([1 => 2]))->pick(2)],
]);
