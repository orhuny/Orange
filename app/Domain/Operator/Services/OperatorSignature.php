<?php

declare(strict_types=1);

namespace App\Domain\Operator\Services;

final class OperatorSignature
{
    public static function compute(
        string $secret,
        string $method,
        string $path,
        string $body,
        int $timestamp,
    ): string {
        $message = strtoupper($method)."\n".$path."\n".$body."\n".$timestamp;

        return hash_hmac('sha256', $message, $secret);
    }

    public static function matches(string $expected, string $given): bool
    {
        return strlen($expected) === strlen($given)
            && hash_equals($expected, $given);
    }
}
