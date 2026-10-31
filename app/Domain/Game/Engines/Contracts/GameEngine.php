<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Contracts;

use App\Domain\Game\Engines\EngineFamily;
use App\Domain\Game\Exceptions\InvalidGameConfig;
use App\Domain\Game\Models\Game;

/**
 * One game type (games.type). Engines own the maths: outcome generation, payout
 * multipliers, the shape of their public state and fairness verification. They never
 * move money — the core caps the multiplier at max win and talks to the wallet.
 */
interface GameEngine
{
    /** The games.type value this engine serves, e.g. 'crash'. */
    public static function type(): string;

    public function family(): EngineFamily;

    /**
     * Validates and normalises the game's engine-specific games.config. Gets the game
     * too, for checks against its other settings (e.g. a payout table against rtp_target).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws InvalidGameConfig
     */
    public function validateConfig(Game $game, array $config): array;

    /** Highest payout multiplier the game can produce, as a decimal string; '0' when unbounded. */
    public function maxMultiplier(Game $game): string;

    public function verifier(): FairnessVerifier;
}
