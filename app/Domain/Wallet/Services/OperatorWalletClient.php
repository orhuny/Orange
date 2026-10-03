<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Exceptions\WalletCallbackFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperatorWalletClient
{
    /** @return array<string, mixed> { balance_after, operator_tx_id, ... } */
    public function dispatch(
        Operator $operator,
        WalletTransactionType $type,
        string $amount,
        string $currency,
        string $playerExternalId,
        string $idempotencyKey,
        array $reference,
    ): array {
        if ($operator->callback_base_url === null) {
            throw new WalletCallbackFailed('operator has no callback_base_url configured', 0);
        }

        $url = rtrim($operator->callback_base_url, '/').'/wallet/'.$type->value;
        $body = [
            'player_external_id' => $playerExternalId,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'tx_id' => $idempotencyKey,
        ];

        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        $signature = OperatorSignature::compute(
            secret: $operator->secret_key,
            method: 'POST',
            path: $path,
            body: $bodyJson,
            timestamp: $timestamp,
        );

        $maxAttempts = max(1, (int) config('wallet.callback.retry_attempts', 3));
        $timeoutSeconds = (int) config('wallet.callback.timeout_seconds', 5);
        $backoffMs = (int) config('wallet.callback.retry_backoff_ms', 500);

        $attempts = 0;
        $lastError = null;
        $lastResponse = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                /** @var Response $response */
                $response = Http::withHeaders([
                    'X-Operator-Id' => $operator->id,
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => $signature,
                    'Idempotency-Key' => $idempotencyKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                    ->timeout($timeoutSeconds)
                    ->withBody($bodyJson, 'application/json')
                    ->post($url);

                if ($response->successful()) {
                    Log::channel('operator')->info('wallet.callback.ok', [
                        'operator_id' => $operator->id,
                        'type' => $type->value,
                        'url' => $url,
                        'attempt' => $attempts,
                        'status' => $response->status(),
                        'tx_id' => $idempotencyKey,
                    ]);

                    return [
                        'attempts' => $attempts,
                        'status' => $response->status(),
                        'body' => $response->json() ?? [],
                    ];
                }

                $lastResponse = [
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'attempt' => $attempts,
                ];

                if ($response->status() >= 400 && $response->status() < 500) {
                    throw new WalletCallbackFailed(
                        "operator wallet rejected with {$response->status()}",
                        $attempts,
                        $lastResponse,
                    );
                }
            } catch (WalletCallbackFailed $e) {
                throw $e;
            } catch (ConnectionException|RequestException|Throwable $e) {
                $lastError = $e;
                $lastResponse = [
                    'status' => 0,
                    'body' => $e->getMessage(),
                    'attempt' => $attempts,
                ];
            }

            if ($attempts < $maxAttempts) {
                usleep($backoffMs * 1000 * (2 ** ($attempts - 1)));
            }
        }

        throw new WalletCallbackFailed(
            "operator wallet unreachable after {$attempts} attempts",
            $attempts,
            $lastResponse,
            $lastError,
        );
    }
}
