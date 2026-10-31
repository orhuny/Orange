<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

use InvalidArgumentException;

/**
 * The provably fair random numbers every instant and session game draws from.
 *
 *   block_k = HMAC_SHA256(key = server_seed, msg = "{client_seed}:{nonce}:{k}"), k = 0, 1, 2, …
 *
 * Each 32-byte block yields eight unsigned 32-bit integers, read big-endian, in order.
 * nextInt(n) draws uniformly from [0, n) by rejection sampling, with no modulo bias.
 * Anyone with the revealed server seed can replay the exact same sequence
 * (docs/operator-integration.md, "Fairness").
 */
final class RandomStream
{
    private int $block = 0;

    private string $buffer = '';

    public function __construct(
        private readonly string $serverSeed,
        private readonly string $clientSeed,
        private readonly int $nonce,
    ) {}

    public function nextUint32(): int
    {
        if (strlen($this->buffer) < 4) {
            $this->buffer .= hash_hmac('sha256', "{$this->clientSeed}:{$this->nonce}:{$this->block}", $this->serverSeed, true);
            $this->block++;
        }

        $value = unpack('N', substr($this->buffer, 0, 4));
        $this->buffer = substr($this->buffer, 4);

        return (int) $value[1];
    }

    /** A uniform integer in [0, $n). */
    public function nextInt(int $n): int
    {
        if ($n < 1 || $n > 2 ** 32) {
            throw new InvalidArgumentException("nextInt() needs 1 <= n <= 2^32, got {$n}.");
        }

        // Values at or above the largest multiple of n are redrawn, so every result is equally likely.
        $limit = intdiv(2 ** 32, $n) * $n;
        do {
            $value = $this->nextUint32();
        } while ($value >= $limit);

        return $value % $n;
    }
}
