<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\Wallet\Enums\WalletTransactionType;
use App\Domain\Wallet\Exceptions\WalletCallbackFailed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Wallet calls to an operator: POST {callback_base_url}/wallet/{debit|credit|rollback}.
 * - A 2xx is the operator's yes.
 * - A 4xx is its definite no (WalletCallbackFailed, never retried).
 * - No answer (unreachable, timed out, a 5xx) is retried within the call's budget, re-sending
 *   the same tx_id.
 * Every attempt that got no answer is logged on the operator channel with its reason.
 */
class OperatorWalletClient
{
    /**
     * @param  array<string, mixed>  $reference
     * @return array<string, mixed> { attempts, status, body }
     */
    public function dispatch(
        Operator $operator,
        WalletTransactionType $type,
        string $amount,
        string $currency,
        string $playerExternalId,
        string $idempotencyKey,
        array $reference,
        ?WalletCallBudget $budget = null,
    ): array {
        if ($operator->callback_base_url === null) {
            throw new WalletCallbackFailed('operator has no callback_base_url configured', 0);
        }

        $budget ??= WalletCallBudget::callback();
        $url = rtrim($operator->callback_base_url, '/').'/wallet/'.$type->value;
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $bodyJson = (string) json_encode([
            'player_external_id' => $playerExternalId,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'tx_id' => $idempotencyKey,
        ], JSON_UNESCAPED_SLASHES);
        $context = ['operator_id' => $operator->id, 'type' => $type->value, 'url' => $url, 'tx_id' => $idempotencyKey];

        $started = microtime(true);
        $attempts = 0;
        $lastError = null;
        $lastResponse = ['status' => 0, 'body' => '', 'attempt' => 0, 'reason' => 'none'];

        while (true) {
            $attempts++;
            $attemptStarted = microtime(true);
            $timeout = $this->attemptTimeout($budget, $started);
            // Signed per attempt, so a retry never falls outside the operator's timestamp window.
            $timestamp = time();

            try {
                $response = Http::withHeaders([
                    'X-Operator-Id' => $operator->id,
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => OperatorSignature::compute(
                        secret: $operator->secret_key,
                        method: 'POST',
                        path: $path,
                        body: $bodyJson,
                        timestamp: $timestamp,
                    ),
                    'Idempotency-Key' => $idempotencyKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                    ->connectTimeout(min($budget->connectTimeoutSeconds, $timeout))
                    ->timeout($timeout)
                    ->withBody($bodyJson, 'application/json')
                    ->post($url);

                if ($response->successful()) {
                    Log::channel('operator')->info('wallet.callback.ok', $context + [
                        'attempt' => $attempts,
                        'status' => $response->status(),
                        'duration_ms' => self::msSince($attemptStarted),
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
                    'reason' => 'http_'.$response->status(),
                ];

                if ($response->clientError()) {
                    throw new WalletCallbackFailed("operator wallet rejected with {$response->status()}", $attempts, $lastResponse);
                }
            } catch (WalletCallbackFailed $e) {
                throw $e;
            } catch (Throwable $e) {
                $lastError = $e;
                $lastResponse = [
                    'status' => 0,
                    'body' => $e->getMessage(),
                    'attempt' => $attempts,
                    'reason' => self::reason($e),
                ];
            }

            Log::channel('operator')->warning('wallet.callback.attempt_failed', $context + [
                'attempt' => $attempts,
                'status' => $lastResponse['status'],
                'reason' => $lastResponse['reason'],
                'error' => Str::limit(is_string($lastResponse['body']) ? $lastResponse['body'] : (string) json_encode($lastResponse['body']), 300),
                'duration_ms' => self::msSince($attemptStarted),
            ]);

            $backoffMs = $budget->backoffMs * (2 ** ($attempts - 1));
            if (! $this->mayRetry($budget, $attempts, $started, $backoffMs)) {
                break;
            }
            usleep($backoffMs * 1000);
        }

        throw new WalletCallbackFailed(
            "operator wallet unreachable after {$attempts} attempts",
            $attempts,
            $lastResponse + ['duration_ms' => self::msSince($started)],
            $lastError,
        );
    }

    /** Seconds this attempt may take: its own timeout, cut short by the call's deadline. */
    private function attemptTimeout(WalletCallBudget $budget, float $started): float
    {
        if ($budget->deadlineSeconds === null) {
            return $budget->timeoutSeconds;
        }

        return max(0.1, min($budget->timeoutSeconds, $budget->deadlineSeconds - (microtime(true) - $started)));
    }

    /** Another attempt only while the budget allows one and, with a deadline, enough time is left for it. */
    private function mayRetry(WalletCallBudget $budget, int $attempts, float $started, int $backoffMs): bool
    {
        if ($attempts >= $budget->maxAttempts) {
            return false;
        }
        if ($budget->deadlineSeconds === null) {
            return true;
        }

        $left = $budget->deadlineSeconds - (microtime(true) - $started) - $backoffMs / 1000;

        return $left >= $budget->minRetrySeconds;
    }

    /** Why a call got no answer, from curl's error number: what to look for when it happens. */
    private static function reason(Throwable $e): string
    {
        $message = $e->getMessage();
        if (preg_match('/cURL error (\d+)/', $message, $m) !== 1) {
            return 'transport';
        }

        return match ((int) $m[1]) {
            6 => 'dns',
            7 => 'connect_failed',
            28 => str_contains($message, 'Connection timed out') || str_contains($message, 'Resolving timed out') ? 'connect_timeout' : 'timeout',
            35, 51, 58, 60 => 'tls',
            52, 55, 56 => 'connection_dropped',
            default => 'transport',
        };
    }

    private static function msSince(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
