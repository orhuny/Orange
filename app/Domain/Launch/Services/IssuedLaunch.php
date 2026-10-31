<?php

declare(strict_types=1);

namespace App\Domain\Launch\Services;

use Carbon\CarbonInterface;

/** A launch token as handed to the operator: the only time the raw token exists. */
final readonly class IssuedLaunch
{
    public function __construct(
        public string $token,
        public string $url,
        public CarbonInterface $expiresAt,
    ) {}
}
