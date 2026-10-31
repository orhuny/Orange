<?php

declare(strict_types=1);

namespace App\Domain\Game\Engines\Contracts;

use App\Domain\Game\Models\Game;

/** Recomputes an outcome from revealed seeds so anyone can check it. Must be pure. */
interface FairnessVerifier
{
    /**
     * Validation rules for the game-specific verification inputs (besides the seeds and nonce).
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $params  validated against rules()
     * @return array<string, mixed>
     */
    public function verify(Game $game, string $serverSeed, string $clientSeed, int $nonce, array $params): array;
}
