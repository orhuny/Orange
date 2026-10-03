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
            // Optional explicit house edge for the verification. If omitted,
            // the service's default divisor is used (config/crash.php). When
            // reproducing a specific round, callers should pass the same
            // house_edge that the round's game had at the time of play.
            'house_edge' => ['nullable', 'numeric', 'gt:0', 'lt:1'],
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

        $divisor = isset($validated['house_edge'])
            ? (int) round(1.0 / (float) $validated['house_edge'])
            : $this->math->defaultDivisor();

        $crashPoint = $this->math->crashFromHmac($hmac, $divisor);

        return response()->json([
            'algorithm' => 'bustabit-style-hmac-sha256-1overN-edge',
            'house_edge_divisor' => $divisor,
            'server_seed_hash' => $hash,
            'expected_hash_matches' => $hashMatches,
            'hmac' => $hmac,
            'crash_point' => $crashPoint,
        ]);
    }
}
