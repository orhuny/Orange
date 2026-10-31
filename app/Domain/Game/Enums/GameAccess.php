<?php

declare(strict_types=1);

namespace App\Domain\Game\Enums;

/** Which operators may play a game. */
enum GameAccess: string
{
    /** Every operator, unless an operator_game row disables it for one (crash). */
    case All = 'all';

    /** Only operators with an enabled operator_game row (the default for new games). */
    case Listed = 'listed';
}
