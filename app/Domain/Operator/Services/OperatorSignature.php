<?php

declare(strict_types=1);

namespace App\Domain\Operator\Services;

final class OperatorSignature
{
    /**
     * hex(HMAC-SHA256(secret, METHOD\nPATH\nBODY\nTIMESTAMP)). Version 2 (opt-in with
     * X-Signature-Version: 2) appends \nIDEMPOTENCY_KEY, so the key is signed too.
     */
    public static function compute(
        string $secret,
        string $method,
        string $path,
        string $body,
        int $timestamp,
        ?string $idempotencyKey = null,
    ): string {
        $message = strtoupper($method)."\n".$path."\n".$body."\n".$timestamp;

        if ($idempotencyKey !== null) {
            $message .= "\n".$idempotencyKey;
        }

        return hash_hmac('sha256', $message, $secret);
    }

    public static function matches(string $expected, string $given): bool
    {
        return strlen($expected) === strlen($given)
            && hash_equals($expected, $given);
    }
}
