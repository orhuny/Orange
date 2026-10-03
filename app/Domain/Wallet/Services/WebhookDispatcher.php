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

        $dispatch = DeliverWebhookJob::dispatch($webhook->id);

        // On the sync queue (dev / no worker) a webhook POST runs INLINE
        // inside the parent HTTP request and pins the operator's only PHP
        // worker for the full HTTP timeout × retry budget. Defer it to the
        // terminate phase so the bet/cashout response can return first.
        // Production setups with a real queue worker keep normal semantics.
        if (! app()->runningInConsole()
            && app()->bound('request')
            && config('queue.default') === 'sync'
        ) {
            $dispatch->afterResponse();
        }

        return $webhook;
    }
}
