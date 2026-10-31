<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Game\Models\Game;
use App\Domain\Launch\Exceptions\InvalidLaunchToken;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /play/{code}?token=… — the page an operator opens (in its iframe) with a launch
 * token. The token is exchanged once for a play session; the page then hosts the game.
 * Without a token, the page resumes the session this tab already holds (a reload).
 */
class PlayPageController
{
    public function __construct(private readonly PlaySessionService $sessions) {}

    public function __invoke(Request $request, string $code): Response
    {
        $game = Game::query()->where('code', $code)->first();
        $frameUrl = $game instanceof Game ? config('games.frontends.'.$game->engineType()) : null;
        if (! $game instanceof Game || ! is_string($frameUrl)) {
            return $this->expired(404);
        }

        $bearer = null;
        $language = null;
        $outerOrigins = (array) config('embed.allowed_origins', []);
        $token = (string) $request->query('token', '');

        if ($token !== '') {
            try {
                [$session, $bearer] = $this->sessions->exchange($token);
            } catch (InvalidLaunchToken) {
                return $this->expired(410);
            }

            if ($session->game_id !== $game->id) {
                $session->forceFill(['revoked_at' => now()])->save();

                return $this->expired(404);
            }

            $language = $session->language;
            $operator = $session->operator;
            $outerOrigins = array_merge($outerOrigins, $operator instanceof Operator ? $operator->embedOrigins() : []);
        }

        return response()
            ->view('play.host', [
                'gameCode' => $game->code,
                'gameName' => $game->name,
                'frameUrl' => url($frameUrl),
                'bearer' => $bearer,
                'language' => $language,
                'outerOrigins' => array_values(array_unique($outerOrigins)),
            ])
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function expired(int $status): Response
    {
        return response()->view('play.expired', [], $status)
            ->header('Cache-Control', 'no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
