<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Engines\Dice\DiceEngine;
use App\Filament\GameEngines\CrashConfigForm;
use App\Filament\GameEngines\DiceConfigForm;

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
        'dice' => DiceEngine::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Launching players into games and the lobby
    |--------------------------------------------------------------------------
    */

    // The page each engine type plays in, embedded by Orange's host pages (/play, /lobby).
    'frontends' => [
        'crash' => '/crash',
        'dice' => '/games/dice',
    ],

    // The admin form for each engine's settings (games.config), shown for games of that type.
    'admin_forms' => [
        'crash' => CrashConfigForm::class,
        'dice' => DiceConfigForm::class,
    ],

    'launch' => [
        // A launch token must be exchanged (the page opened) within this time, once.
        'token_ttl_seconds' => 120,
        // How long a play session lasts after the page opens.
        'session_ttl_seconds' => 4 * 3600,
    ],

    // Which games' rounds also go out on the legacy `crash` channel (afq's /crash page):
    // null = every crash-type game, as before there were several games; or a list of codes.
    // Every round is also broadcast on game.{code}.
    'legacy_channel_games' => null,
];
