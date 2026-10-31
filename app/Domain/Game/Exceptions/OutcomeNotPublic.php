<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use RuntimeException;

class OutcomeNotPublic extends RuntimeException
{
    public static function forRound(GameRound $round): self
    {
        $state = $round->getAttribute('state');
        $state = $state instanceof RoundState ? $state->value : (string) $state;

        return new self("Round [{$round->round_uuid}] is in state [{$state}]; its outcome is still secret.");
    }
}
