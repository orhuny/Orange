<?php

declare(strict_types=1);

namespace App\Domain\Launch\Services;

use App\Domain\Launch\Exceptions\InvalidLaunchToken;
use App\Domain\Launch\Models\LaunchToken;
use App\Domain\Launch\Models\PlaySession;
use Illuminate\Support\Facades\DB;

/** Turns a launch token into a play session (once) and authenticates the page's requests. */
final class PlaySessionService
{
    /** How often last_seen_at is written, at most. */
    private const TOUCH_EVERY_SECONDS = 60;

    /**
     * @return array{0: PlaySession, 1: string} the session and its bearer token (never stored)
     *
     * @throws InvalidLaunchToken
     */
    public function exchange(string $launchToken): array
    {
        $hash = LaunchService::hash($launchToken);

        return DB::transaction(function () use ($hash): array {
            // Single use: only one request can flip used_at, however many arrive at once.
            $claimed = LaunchToken::query()
                ->where('token_hash', $hash)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);

            $launch = $claimed === 1 ? LaunchToken::query()->with('operator')->where('token_hash', $hash)->first() : null;
            // A suspended or disabled operator's links stop working, like its API keys do.
            if (! $launch instanceof LaunchToken || $launch->operator?->isActive() !== true) {
                throw new InvalidLaunchToken('This link has expired or was already used.');
            }

            $token = 'ps_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $session = PlaySession::create([
                'token_hash' => LaunchService::hash($token),
                'operator_id' => $launch->operator_id,
                'player_id' => $launch->player_id,
                'game_id' => $launch->game_id,
                'lobby' => $launch->game_id === null,
                'currency' => $launch->currency,
                'language' => $launch->language,
                'return_url' => $launch->return_url,
                'expires_at' => now()->addSeconds((int) config('games.launch.session_ttl_seconds', 4 * 3600)),
            ]);

            return [$session, $token];
        });
    }

    public function authenticate(string $bearerToken): ?PlaySession
    {
        $session = PlaySession::query()->with('operator')->where('token_hash', LaunchService::hash($bearerToken))->first();
        // Sessions end with their operator: a suspended operator takes no more bets.
        if (! $session instanceof PlaySession || ! $session->isUsable() || $session->operator?->isActive() !== true) {
            return null;
        }

        $lastSeen = $session->getAttribute('last_seen_at');
        if ($lastSeen === null || $lastSeen->diffInSeconds(now(), true) >= self::TOUCH_EVERY_SECONDS) {
            $session->forceFill(['last_seen_at' => now()])->save();
        }

        return $session;
    }
}
