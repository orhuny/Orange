<?php

declare(strict_types=1);

use App\Domain\ProvablyFair\Services\RandomStream;

// Golden values computed with an independent implementation (Python hmac/struct);
// never regenerate them from the code under test.

it('reads unsigned 32-bit big-endian integers from successive HMAC blocks', function (): void {
    $stream = new RandomStream('orange-dice-seed-0001', 'player-seed', 1);

    $values = array_map(fn () => $stream->nextUint32(), range(1, 10));

    // The 9th and 10th values come from block k=1.
    expect($values)->toBe([3346754430, 346164815, 1462846959, 872183774, 1722258943, 570532536, 2397100589, 3308012972, 2554825052, 2774620441]);
});

it('draws uniformly without modulo bias, redrawing values above the last full multiple', function (): void {
    // n = 3 * 2^30: the first draw (3661401714) is >= the limit and is redrawn.
    expect((new RandomStream('orange-reject-seed', '0', 1))->nextInt(3 * 2 ** 30))->toBe(2941989251);
});

it('rolls the published dice values', function (string $server, string $client, int $nonce, int $roll): void {
    expect((new RandomStream($server, $client, $nonce))->nextInt(10_000))->toBe($roll);
})->with([
    ['orange-dice-seed-0001', 'player-seed', 1, 4430],
    ['orange-dice-seed-0001', 'player-seed', 2, 4441],
    ['orange-dice-seed-0002', 'abc', 42, 891],
    ['9f2c4e1ab0d34c7e8a5b6f1d2e3c4b5a6978877665544332211ffeeddccbbaa0', 'house', 7, 9539],
]);

it('stays within range and refuses impossible ranges', function (): void {
    $stream = new RandomStream('seed', 'client', 1);
    foreach (range(1, 2000) as $_) {
        expect($stream->nextInt(7))->toBeGreaterThanOrEqual(0)->toBeLessThan(7);
    }

    expect(fn () => $stream->nextInt(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $stream->nextInt(2 ** 32 + 1))->toThrow(InvalidArgumentException::class);
});
