<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Jobs;

use App\Domain\Wallet\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Carries the parameters needed to fire an HMAC-signed wallet callback
 * to the operator. Dispatched by WalletService::record and handled by
 * a `queue:work` worker so the originating HTTP request can return
 * immediately (php artisan serve would otherwise hold the bet response
 * for the duration of the callback's timeout × retry budget).
 */
class DispatchWalletCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(WalletService $wallet): void
    {
        $wallet->dispatchCallback($this->payload);
    }
}
