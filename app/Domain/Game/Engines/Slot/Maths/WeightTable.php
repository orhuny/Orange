<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Slot\Maths;

use Closure;
use InvalidArgumentException;

/**
 * A weighted draw: one call to nextInt(total) picks a value. The entries keep their
 * listed order, and r falls in the first entry whose cumulative weight exceeds it, so
 * anyone with the table can replay a draw (SlotVerifier::describe() publishes it).
 */
final class WeightTable
{
    /** @var list<int> */
    public readonly array $values;

    /** @var list<int> */
    private readonly array $cumulative;

    public readonly int $total;

    /** @param  array<int, int>  $weights  value => weight (> 0), in draw order */
    public function __construct(array $weights)
    {
        $values = [];
        $cumulative = [];
        $sum = 0;
        foreach ($weights as $value => $weight) {
            if ($weight <= 0) {
                throw new InvalidArgumentException("Weight for [{$value}] must be positive, got {$weight}.");
            }
            $sum += $weight;
            $values[] = $value;
            $cumulative[] = $sum;
        }
        if ($values === []) {
            throw new InvalidArgumentException('A weight table needs at least one entry.');
        }

        $this->values = $values;
        $this->cumulative = $cumulative;
        $this->total = $sum;
    }

    /** The value r (0 <= r < total) lands on. */
    public function pick(int $r): int
    {
        foreach ($this->cumulative as $i => $upTo) {
            if ($r < $upTo) {
                return $this->values[$i];
            }
        }

        throw new InvalidArgumentException("r must be below {$this->total}, got {$r}.");
    }

    /** @param  Closure(int): int  $nextInt  a uniform integer in [0, n) */
    public function draw(Closure $nextInt): int
    {
        return $this->pick($nextInt($this->total));
    }

    /** @return array<int, int> value => weight, in draw order */
    public function weights(): array
    {
        $weights = [];
        $previous = 0;
        foreach ($this->values as $i => $value) {
            $weights[$value] = $this->cumulative[$i] - $previous;
            $previous = $this->cumulative[$i];
        }

        return $weights;
    }
}
