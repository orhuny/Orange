<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines;

enum EngineFamily: string
{
    /** Shared rounds with a betting phase and a schedule (crash). */
    case RoundBased = 'round';

    /** The bet resolves immediately (dice, plinko). */
    case Instant = 'instant';

    /** A short multi-step player session (mines). */
    case Session = 'session';
}
