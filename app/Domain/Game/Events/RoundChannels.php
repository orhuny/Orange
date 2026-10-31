<?php

declare(strict_types=1);

namespace App\Domain\Game\Events;

use App\Domain\Game\Models\GameRound;
use Illuminate\Broadcasting\Channel;

/**
 * Where a round's events are broadcast: its own channel, its game's channel
 * (game.{code}), and the legacy `crash` channel that afq's /crash page listens to.
 */
final class RoundChannels
{
    /** @return list<Channel> */
    public static function for(GameRound $round): array
    {
        $channels = [new Channel("round.{$round->round_uuid}")];
        $game = $round->game;

        if (self::onLegacyChannel($round)) {
            $channels[] = new Channel('crash');
        }

        if ($game !== null) {
            $channels[] = new Channel("game.{$game->code}");
        }

        return $channels;
    }

    /**
     * Every crash-type game broadcasts on `crash`, as all rounds did before there were
     * several games, unless config('games.legacy_channel_games') narrows it to a list of codes.
     */
    private static function onLegacyChannel(GameRound $round): bool
    {
        $game = $round->game;
        if ($game === null) {
            return true;
        }

        $only = config('games.legacy_channel_games');

        return $game->getAttribute('type') === 'crash'
            && (! is_array($only) || in_array($game->code, $only, true));
    }
}
