<?php

declare(strict_types=1);

namespace App\Domain\Launch\Exceptions;

use RuntimeException;

/** A launch token that is unknown, already used or expired. */
class InvalidLaunchToken extends RuntimeException {}
