<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Contracts;

use App\Domain\Game\Engines\InstantResult;
use App\Domain\Game\Exceptions\InvalidBetParams;
use App\Domain\Game\Models\Game;
use App\Domain\ProvablyFair\Services\RandomStream;

/** A game whose bet resolves at once (dice, plinko). */
interface InstantEngine extends GameEngine
{
    /**
     * Validates and normalises the player's choices (dice: target, direction).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws InvalidBetParams
     */
    public function validateParams(Game $game, array $params): array;

    /**
     * Plays one bet. Must be pure and deterministic: the same game settings, params and
     * random stream always give the same result, so it can be verified later.
     *
     * @param  array<string, mixed>  $params  as returned by validateParams()
     */
    public function play(Game $game, array $params, RandomStream $rng): InstantResult;
}
