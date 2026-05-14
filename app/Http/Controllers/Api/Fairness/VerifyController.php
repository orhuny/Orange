<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Fairness;

use App\Domain\ProvablyFair\Services\ProvablyFairService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerifyController
{
    public function __construct(private readonly ProvablyFairService $math) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server_seed' => ['required', 'string', 'min:8', 'max:128'],
            'client_seed' => ['required', 'string', 'min:1', 'max:128'],
            'nonce' => ['required', 'integer', 'min:0'],
            'expected_server_seed_hash' => ['nullable', 'string', 'size:64'],
        ]);

        $serverSeed = $validated['server_seed'];
        $clientSeed = $validated['client_seed'];
        $nonce = (int) $validated['nonce'];

        $hash = $this->math->hashSeed($serverSeed);
        $expectedHash = $request->input('expected_server_seed_hash');

        $hashMatches = $expectedHash === null
            ? null
            : hash_equals($hash, (string) $expectedHash);

        $hmac = $this->math->hmac($serverSeed, $clientSeed, $nonce);
        $crashPoint = $this->math->crashFromHmac($hmac);

        return response()->json([
            'algorithm' => 'bustabit-style-hmac-sha256-1over33-edge',
            'server_seed_hash' => $hash,
            'expected_hash_matches' => $hashMatches,
            'hmac' => $hmac,
            'crash_point' => $crashPoint,
        ]);
    }
}
