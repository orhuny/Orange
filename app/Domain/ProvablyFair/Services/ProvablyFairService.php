<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

use App\Domain\Game\Models\Game;
use InvalidArgumentException;

final class ProvablyFairService
{
    public const MIN_MULTIPLIER = 1.00;

    /**
     * Default house edge divisor for tests / public verification when no
     * specific game is in scope. Wired to config/crash.php, which in turn
     * reads GAME_CRASH_HOUSE_EDGE_DIVISOR from .env.
     */
    public function defaultDivisor(): int
    {
        return (int) config('crash.house_edge_divisor', 33);
    }

    /**
     * Derive the bustabit-style "1-in-N" divisor from a game's configured
     * house edge. With house_edge = 0.03, divisor = 33 (≈ 3.03% boundary
     * hits collapse to 1.00x, giving ≈ 96.97% RTP).
     */
    public function divisorForGame(Game $game): int
    {
        $edge = (float) $game->house_edge;

        if ($edge <= 0.0 || $edge >= 1.0) {
            throw new InvalidArgumentException(
                "Game '{$game->code}' has house_edge={$edge} which is outside the valid (0, 1) range required to derive a divisor."
            );
        }

        // Round so the on-screen 0.03 → 33 and 0.01 → 100 etc.
        return (int) round(1.0 / $edge);
    }

    public function hmac(string $serverSeed, string $clientSeed, int $nonce): string
    {
        return hash_hmac('sha256', $clientSeed.':'.$nonce, $serverSeed);
    }

    /**
     * Compute a crash point from raw seeds. When $divisor is omitted the
     * service falls back to defaultDivisor() — production round generation
     * should always pass a game-derived divisor via divisorForGame().
     */
    public function crashPoint(
        string $serverSeed,
        string $clientSeed,
        int $nonce,
        ?int $divisor = null,
    ): float {
        return $this->crashFromHmac(
            $this->hmac($serverSeed, $clientSeed, $nonce),
            $divisor,
        );
    }

    public function crashFromHmac(string $hmac, ?int $divisor = null): float
    {
        $divisor ??= $this->defaultDivisor();

        if ($divisor < 2) {
            throw new InvalidArgumentException("Divisor must be >= 2, got {$divisor}.");
        }

        $hShort = (int) hexdec(substr($hmac, 0, 8));

        if ($hShort % $divisor === 0) {
            return self::MIN_MULTIPLIER;
        }

        $h = (int) hexdec(substr($hmac, 0, 13));
        $e = 2 ** 52;
        $crash = floor((100 * $e - $h) / ($e - $h)) / 100;

        return max(self::MIN_MULTIPLIER, round($crash, 2));
    }

    public function hashSeed(string $serverSeed): string
    {
        return hash('sha256', $serverSeed);
    }
}
