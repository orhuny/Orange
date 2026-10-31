<?php

declare(strict_types=1);

namespace App\Domain\Game\Exceptions;

use App\Domain\Game\Models\Game;
use RuntimeException;

class GameNotAvailable extends RuntimeException
{
    /** @param  'game_not_enabled'|'game_unavailable'  $reason  the API error code */
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function notEnabled(Game $game): self
    {
        return new self("Game [{$game->code}] is not enabled for this operator.", 'game_not_enabled');
    }

    public static function unavailable(Game $game): self
    {
        return new self("Game [{$game->code}] is not available right now.", 'game_unavailable');
    }
}
