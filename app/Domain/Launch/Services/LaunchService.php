<?php

declare(strict_types=1);

namespace App\Domain\Launch\Services;

use App\Domain\Game\Exceptions\CurrencyNotSupported;
use App\Domain\Game\Exceptions\GameNotAvailable;
use App\Domain\Game\Limits\GameLimits;
use App\Domain\Game\Models\Game;
use App\Domain\Launch\Models\LaunchToken;
use App\Domain\Operator\Models\Operator;
use App\Domain\Operator\Models\Player;

/** Issues the short-lived, single-use tokens that open a game or the lobby for a player. */
final class LaunchService
{
    public function __construct(private readonly GameLimits $limits) {}

    /**
     * @param  Game|null  $game  null launches the lobby
     *
     * @throws GameNotAvailable
     * @throws CurrencyNotSupported
     */
    public function issue(
        Operator $operator,
        ?Game $game,
        string $externalPlayerId,
        string $currency,
        ?string $language = null,
        ?string $returnUrl = null,
    ): IssuedLaunch {
        $currency = strtoupper($currency);

        if ($game instanceof Game) {
            // Refuse up front what a bet would refuse later: disabled game, unsupported currency,
            // or a game this operator plays in relay mode.
            $this->limits->for($game, $operator, $currency);
            $this->limits->assertDirectPlay($game, $operator);
        }

        $player = Player::query()->firstOrCreate(
            ['operator_id' => $operator->id, 'external_player_id' => $externalPlayerId],
            ['currency' => $currency, 'status' => 'active'],
        );

        $token = 'lt_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expiresAt = now()->addSeconds((int) config('games.launch.token_ttl_seconds', 120));

        LaunchToken::create([
            'token_hash' => self::hash($token),
            'operator_id' => $operator->id,
            'player_id' => $player->id,
            'game_id' => $game?->id,
            'currency' => $currency,
            'language' => $language,
            'return_url' => $returnUrl,
            'expires_at' => $expiresAt,
        ]);

        $url = $game instanceof Game ? url("/play/{$game->code}") : url('/lobby');

        return new IssuedLaunch($token, $url.'?token='.$token, $expiresAt);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
