<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use InvalidArgumentException;

/**
 * One RTP variant of a model: the weight tables every draw uses. Immutable once a bet
 * has been played with it (the bet's params name it, and verification replays it), so a
 * retune is a new variant id, never an edit.
 */
final readonly class Variant
{
    /**
     * @param  string  $id  e.g. "v1-9650"
     * @param  string  $rtp  the long-run RTP measured for every mode, 4 dp, e.g. "0.9650"
     * @param  list<WeightTable>  $base  symbols per column, base game
     * @param  list<WeightTable>  $ante  symbols per column, base game with the ante bet
     * @param  list<WeightTable>  $buy  symbols per column for a bought trigger spin (no scatters)
     * @param  list<WeightTable>  $free  symbols per column in free spins (with multipliers)
     * @param  WeightTable  $multipliers  a multiplier symbol's value
     * @param  WeightTable  $buyScatters  how many scatters a bought trigger spin places
     */
    public function __construct(
        public string $id,
        public string $rtp,
        public array $base,
        public array $ante,
        public array $buy,
        public array $free,
        public WeightTable $multipliers,
        public WeightTable $buyScatters,
    ) {
        foreach (['base' => $base, 'ante' => $ante, 'buy' => $buy, 'free' => $free] as $name => $columns) {
            if ($columns === []) {
                throw new InvalidArgumentException("Variant [{$id}] needs a list of column tables for [{$name}].");
            }
        }
    }

    /**
     * The column tables a reel set uses.
     *
     * @return list<WeightTable>
     */
    public function set(string $name): array
    {
        return match ($name) {
            'base' => $this->base,
            'ante' => $this->ante,
            'buy' => $this->buy,
            'free' => $this->free,
            default => throw new InvalidArgumentException("Unknown reel set [{$name}]."),
        };
    }
}
