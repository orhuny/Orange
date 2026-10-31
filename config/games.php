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
];
