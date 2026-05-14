<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Operator\Models\Operator;
use App\Domain\Wallet\Enums\WebhookEvent;
use App\Domain\Wallet\Enums\WebhookStatus;
use App\Domain\Wallet\Jobs\DeliverWebhookJob;
use App\Domain\Wallet\Models\Webhook;

class WebhookDispatcher
{
    /** @param  array<string, mixed>  $payload */
    public function dispatch(Operator $operator, WebhookEvent $event, array $payload): Webhook
    {
        $webhook = Webhook::create([
            'operator_id' => $operator->id,
            'event' => $event,
            'payload' => $payload,
            'status' => WebhookStatus::Pending,
            'attempts' => 0,
            'next_retry_at' => now(),
        ]);

        DeliverWebhookJob::dispatch($webhook->id);

        return $webhook;
    }
}
