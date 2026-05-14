<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Game\Enums\RoundState;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Enums\OperatorStatus;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Services\OperatorSignature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PlaceDemoBetCommand extends Command
{
    protected $signature = 'crash:bet
        {amount=1.00 : Bet amount in EUR}
        {--auto-cashout= : Auto-cash-out at this multiplier (e.g. 2.00)}
        {--player=demo-player : External player ID}
        {--wait=15 : Seconds to wait for a betting round if none is open}';

    protected $description = 'Place a demo bet via the live HMAC-signed REST API. Auto-creates a demo operator on first run.';

    public function handle(): int
    {
        $operator = $this->ensureDemoOperator();
        $round = $this->awaitBettingRound((int) $this->option('wait'));

        if (! $round) {
            $this->error('No round in betting state — is `crash:loop` running in another terminal?');

            return self::FAILURE;
        }

        $body = json_encode([
            'round_uuid' => $round->round_uuid,
            'external_player_id' => (string) $this->option('player'),
            'amount' => $this->argument('amount'),
            'currency' => 'EUR',
            'auto_cashout_at' => $this->option('auto-cashout') !== null
                ? (float) $this->option('auto-cashout') : null,
        ]);

        $key = 'demo-bet-'.Str::lower(Str::random(8));
        $response = $this->signedPost('POST', '/v1/bets', $body, $operator, $key);

        if (! $response->successful()) {
            $this->error("Bet rejected: HTTP {$response->status()}");
            $this->line($response->body());

            return self::FAILURE;
        }

        $payload = $response->json();
        $this->info("Bet placed → bet_id={$payload['bet_id']} on round ".substr($round->round_uuid, 0, 8).'…');
        $this->table(
            ['field', 'value'],
            collect($payload)->map(fn ($v, $k) => [$k, is_scalar($v) ? (string) $v : json_encode($v)])->values()->all(),
        );

        if ($this->option('auto-cashout') === null) {
            $this->newLine();
            $this->line("To cash out manually: <fg=yellow>php artisan crash:cashout {$payload['bet_id']}</>");
        } else {
            $this->line("Auto-cashout will fire at {$this->option('auto-cashout')}x.");
        }

        return self::SUCCESS;
    }

    private function ensureDemoOperator(): Operator
    {
        $operator = Operator::query()->where('slug', 'demo-operator')->first();

        if ($operator instanceof Operator) {
            return $operator;
        }

        $secret = bin2hex(random_bytes(32));

        return Operator::create([
            'name' => 'Demo Operator',
            'slug' => 'demo-operator',
            'status' => OperatorStatus::Active,
            'callback_base_url' => null,
            'secret_key' => $secret,
            'ip_whitelist' => [],
            'config' => ['demo' => true],
        ]);
    }

    private function awaitBettingRound(int $maxSeconds): ?GameRound
    {
        $deadline = microtime(true) + $maxSeconds;

        while (microtime(true) < $deadline) {
            $round = GameRound::query()
                ->where('state', RoundState::Betting)
                ->latest('id')
                ->first();

            if ($round) {
                return $round;
            }

            $this->line('  …no round in betting state yet, polling…');
            sleep(1);
        }

        return null;
    }

    private function signedPost(string $method, string $path, string $body, Operator $operator, string $idempotencyKey): Response
    {
        $timestamp = time();
        $signature = OperatorSignature::compute(
            secret: $operator->secret_key,
            method: $method,
            path: $path,
            body: $body,
            timestamp: $timestamp,
        );

        $url = rtrim((string) config('app.url', 'http://localhost:8000'), '/').$path;

        return Http::withHeaders([
            'X-Operator-Id' => $operator->id,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
            'Idempotency-Key' => $idempotencyKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->withBody($body, 'application/json')
            ->post($url);
    }
}
