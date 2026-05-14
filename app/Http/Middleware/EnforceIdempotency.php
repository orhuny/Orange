<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Operator\Models\Operator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotency
{
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), self::MUTATION_METHODS, true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $this->error(400, 'missing_idempotency_key');
        }

        $operator = $request->attributes->get('operator');

        if (! $operator instanceof Operator) {
            return $this->error(500, 'operator_not_resolved');
        }

        $cacheKey = "idempotency:{$operator->id}:{$key}";
        $payloadHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if ($cached['payload_hash'] !== $payloadHash) {
                return $this->error(409, 'idempotency_conflict');
            }

            return response()
                ->json($cached['body'], $cached['status'])
                ->header('Idempotent-Replay', 'true');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->isSuccessful() || $response->isClientError()) {
            $body = $response instanceof JsonResponse
                ? $response->getData(true)
                : json_decode($response->getContent() ?: 'null', true);

            $ttl = (int) config('operator.idempotency_ttl_hours') * 3600;

            Cache::put($cacheKey, [
                'payload_hash' => $payloadHash,
                'status' => $response->getStatusCode(),
                'body' => $body,
            ], $ttl);
        }

        return $response;
    }

    private function error(int $status, string $reason): JsonResponse
    {
        return response()->json(['error' => $reason], $status);
    }
}
