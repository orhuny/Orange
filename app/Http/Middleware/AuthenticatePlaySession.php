<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Launch\Services\PlaySessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The player API (/play-api): requests carry the play session's bearer token. */
class AuthenticatePlaySession
{
    public function __construct(private readonly PlaySessionService $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $session = $token === '' ? null : $this->sessions->authenticate($token);

        if ($session === null) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        $request->attributes->set('play_session', $session);

        return $next($request);
    }
}
