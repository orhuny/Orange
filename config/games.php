<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;

return [
    /*
    |--------------------------------------------------------------------------
    | Game engines
    |--------------------------------------------------------------------------
    |
    | games.type => engine class. Adding a game type is one line here plus the
    | engine itself; see docs/adding-a-game.md.
    |
    */

    'engines' => [
        'crash' => CrashEngine::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Launching players into games and the lobby
    |--------------------------------------------------------------------------
    */

    'launch' => [
        // A launch token must be exchanged (the page opened) within this time, once.
        'token_ttl_seconds' => 120,
        // How long a play session lasts after the page opens.
        'session_ttl_seconds' => 4 * 3600,
    ],

    // The game whose rounds are also broadcast on the legacy `crash` channel (afq's /crash).
    'legacy_channel_game' => 'crash',
];
