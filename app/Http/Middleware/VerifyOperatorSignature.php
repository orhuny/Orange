<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyOperatorSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $operatorId = $request->header('X-Operator-Id');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! $operatorId || ! $timestamp || ! $signature) {
            return $this->unauthorized('missing_signature_headers');
        }

        if (! ctype_digit((string) $timestamp)) {
            return $this->unauthorized('invalid_timestamp');
        }

        $timestamp = (int) $timestamp;
        $window = (int) config('operator.timestamp_window_seconds');

        if (abs(time() - $timestamp) > $window) {
            return $this->unauthorized('timestamp_outside_window');
        }

        $operator = Operator::find($operatorId);

        if (! $operator) {
            return $this->unauthorized('unknown_operator');
        }

        if (! $operator->isActive()) {
            return $this->unauthorized('operator_inactive');
        }

        $expected = OperatorSignature::compute(
            secret: $operator->secret_key,
            method: $request->method(),
            path: '/'.ltrim($request->path(), '/'),
            body: $request->getContent(),
            timestamp: $timestamp,
        );

        if (! OperatorSignature::matches($expected, $signature)) {
            return $this->unauthorized('signature_mismatch');
        }

        $replayKey = "operator:{$operator->id}:nonce:{$timestamp}:{$signature}";
        $ttl = (int) config('operator.replay_cache_ttl_seconds');

        if (! Cache::add($replayKey, true, $ttl)) {
            return $this->unauthorized('replay_detected');
        }

        $request->attributes->set('operator', $operator);

        return $next($request);
    }

    private function unauthorized(string $reason): JsonResponse
    {
        return response()->json([
            'error' => 'unauthorized',
            'reason' => $reason,
        ], 401);
    }
}
