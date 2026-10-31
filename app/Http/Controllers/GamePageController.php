<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Models\Game;
use App\Domain\Operator\Models\Operator;
use Illuminate\Contracts\View\View;

/**
 * GET /games/{type} — an engine's game page (resources/views/games/{type}.blade.php),
 * embedded by Orange's host pages (direct play) or by an operator page (relay play).
 * A new game type adds its view; nothing here changes.
 */
class GamePageController
{
    public function __construct(private readonly EngineRegistry $engines) {}

    public function __invoke(string $type): View
    {
        abort_unless($this->engines->has($type) && view()->exists("games.{$type}"), 404);

        $game = Game::query()->where('type', $type)->where('status', '!=', 'draft')->orderBy('id')->first();
        $engine = $this->engines->for($type);

        return view("games.{$type}", [
            'game' => $game,
            'rtp' => $game instanceof Game ? number_format((float) $game->rtp_target, 4, '.', '') : null,
            'config' => $game instanceof Game ? $engine->validateConfig($game, (array) $game->config) : [],
            // Operators embed game pages directly (relay play); Orange's host pages do from our own origin.
            'allowedOrigins' => array_values(array_unique(array_merge((array) config('embed.allowed_origins', []), Operator::allEmbedOrigins()))),
        ]);
    }
}
