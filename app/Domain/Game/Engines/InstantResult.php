<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines;

/** What an instant game produced: the public outcome and the multiplier the bet is paid at ('0' on a loss). */
final readonly class InstantResult
{
    /** @param  array<string, mixed>  $outcome */
    public function __construct(
        public array $outcome,
        public bool $won,
        public string $multiplier,
    ) {}
}
