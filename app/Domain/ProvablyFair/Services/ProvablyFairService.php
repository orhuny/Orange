<?php

declare(strict_types=1);

namespace App\Domain\ProvablyFair\Services;

final class ProvablyFairService
{
    public const HOUSE_EDGE_DIVISOR = 33;

    public const MIN_MULTIPLIER = 1.00;

    public function hmac(string $serverSeed, string $clientSeed, int $nonce): string
    {
        return hash_hmac('sha256', $clientSeed.':'.$nonce, $serverSeed);
    }

    public function crashPoint(string $serverSeed, string $clientSeed, int $nonce): float
    {
        $hmac = $this->hmac($serverSeed, $clientSeed, $nonce);

        return $this->crashFromHmac($hmac);
    }

    public function crashFromHmac(string $hmac): float
    {
        $hShort = (int) hexdec(substr($hmac, 0, 8));

        if ($hShort % self::HOUSE_EDGE_DIVISOR === 0) {
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
