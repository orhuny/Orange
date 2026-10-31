<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Launch\Exceptions\InvalidLaunchToken;
use App\Domain\Launch\Services\PlaySessionService;
use App\Domain\Operator\Models\Operator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /lobby?token=… — the lobby an operator opens (in its iframe) with a lobby launch
 * token. Games open inside the same page, which hosts them like /play/{code} does.
 */
class LobbyPageController
{
    public function __construct(private readonly PlaySessionService $sessions) {}

    public function __invoke(Request $request): Response
    {
        $bearer = null;
        $theme = (new Operator)->lobbyTheme();
        $outerOrigins = (array) config('embed.allowed_origins', []);
        $token = (string) $request->query('token', '');

        if ($token !== '') {
            try {
                [$session, $bearer] = $this->sessions->exchange($token);
            } catch (InvalidLaunchToken) {
                return $this->expired(410);
            }

            if (! $session->lobby) {
                $session->forceFill(['revoked_at' => now()])->save();

                return $this->expired(404);
            }

            $operator = $session->operator;
            if ($operator instanceof Operator) {
                $theme = $operator->lobbyTheme();
                $outerOrigins = array_merge($outerOrigins, $operator->embedOrigins());
            }
        }

        return response()
            ->view('play.lobby', [
                'bearer' => $bearer,
                'theme' => $theme,
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
