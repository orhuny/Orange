<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Operator\Services\OperatorSignature;
use App\Domain\Wallet\Enums\WebhookStatus;
use App\Domain\Wallet\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $webhookId) {}

    public function handle(): void
    {
        /** @var Webhook|null $webhook */
        $webhook = Webhook::with('operator')->find($this->webhookId);

        if (! $webhook || $webhook->status === WebhookStatus::Delivered || $webhook->status === WebhookStatus::DeadLetter) {
            return;
        }

        $operator = $webhook->operator;
        if ($operator->callback_base_url === null) {
            $this->markFailed($webhook, ['error' => 'no_callback_url'], terminal: true);

            return;
        }

        $url = rtrim($operator->callback_base_url, '/').'/webhooks';
        $body = ['event' => $webhook->event->value, 'data' => $webhook->payload];
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

        $webhook->increment('attempts');

        try {
            $response = Http::withHeaders([
                'X-Operator-Id' => $operator->id,
                'X-Timestamp' => (string) $timestamp,
                'X-Signature' => $signature,
                'X-Webhook-Event' => $webhook->event->value,
                'Idempotency-Key' => 'wh-'.$webhook->id,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout((int) config('wallet.webhooks.timeout_seconds', 5))
                ->withBody($bodyJson, 'application/json')
                ->post($url);

            if ($response->successful()) {
                $webhook->forceFill([
                    'status' => WebhookStatus::Delivered,
                    'delivered_at' => now(),
                    'last_response' => ['status' => $response->status(), 'body' => $response->json() ?? $response->body()],
                    'next_retry_at' => null,
                ])->save();

                return;
            }

            $terminal = $response->status() >= 400 && $response->status() < 500;
            $this->markFailed($webhook, ['status' => $response->status(), 'body' => $response->json() ?? $response->body()], terminal: $terminal);
        } catch (Throwable $e) {
            $this->markFailed($webhook, ['error' => $e->getMessage()], terminal: false);
        }
    }

    private function markFailed(Webhook $webhook, array $response, bool $terminal): void
    {
        $maxAttempts = (int) config('wallet.webhooks.max_attempts', 5);
        $backoff = (array) config('wallet.webhooks.retry_backoff_seconds', [10, 30, 120, 600, 1800]);

        if ($terminal || $webhook->attempts >= $maxAttempts) {
            $webhook->forceFill([
                'status' => WebhookStatus::DeadLetter,
                'last_response' => $response,
                'next_retry_at' => null,
            ])->save();

            return;
        }

        $delaySeconds = $backoff[$webhook->attempts - 1] ?? end($backoff);

        $webhook->forceFill([
            'status' => WebhookStatus::Failed,
            'last_response' => $response,
            'next_retry_at' => now()->addSeconds($delaySeconds),
        ])->save();

        self::dispatch($webhook->id)->delay(now()->addSeconds($delaySeconds));
    }
}
