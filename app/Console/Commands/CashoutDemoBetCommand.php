<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Models\Bet;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CashoutDemoBetCommand extends Command
{
    protected $signature = 'crash:cashout {bet : Bet ID to cash out}';

    protected $description = 'Cash out a previously-placed demo bet via the live HMAC-signed REST API.';

    public function handle(): int
    {
        $betId = (int) $this->argument('bet');

        $bet = Bet::query()->find($betId);
        if (! $bet) {
            $this->error("Bet #{$betId} not found.");

            return self::FAILURE;
        }

        $operator = Operator::query()->whereKey($bet->operator_id)->first();
        if (! $operator) {
            $this->error("Operator for bet #{$betId} is missing.");

            return self::FAILURE;
        }

        $body = '[]';
        $path = "/v1/bets/{$betId}/cashout";
        $key = 'demo-cash-'.Str::lower(Str::random(8));
        $timestamp = time();

        $signature = OperatorSignature::compute(
            secret: $operator->secret_key,
            method: 'POST',
            path: $path,
            body: $body,
            timestamp: $timestamp,
        );

        $url = rtrim((string) config('app.url', 'http://localhost:8000'), '/').$path;

        $response = Http::withHeaders([
            'X-Operator-Id' => $operator->id,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Idempotency-Key' => $key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->withBody($body, 'application/json')
            ->post($url);

        if (! $response->successful()) {
            $this->error("Cashout failed: HTTP {$response->status()}");
            $this->line($response->body());

            return self::FAILURE;
        }

        $payload = $response->json();
        $this->info("Cashout settled @ {$payload['multiplier']}x → payout {$payload['payout_amount']} EUR");
        $this->table(
            ['field', 'value'],
            collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) ? (string) $v : json_encode($v)])->values()->all(),
        );

        return self::SUCCESS;
    }
}
