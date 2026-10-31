<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Exceptions;

use RuntimeException;

/** A seed pair can't be rotated (and so revealed) while a bet on it is still unresolved. */
class SeedInUse extends RuntimeException {}
