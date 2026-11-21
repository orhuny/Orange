<?php

declare(strict_types=1);

use App\Domain\Game\Engines\Crash\CrashEngine;
use App\Domain\Game\Engines\Dice\DiceEngine;
use App\Domain\Game\Engines\Slot\SlotEngine;
use App\Filament\GameEngines\CrashConfigForm;
use App\Filament\GameEngines\DiceConfigForm;
use App\Filament\GameEngines\SlotConfigForm;

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
        'slot' => SlotEngine::class,
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
        'slot' => '/games/slot',
    ],

    // The admin form for each engine's settings (games.config), shown for games of that type.
    'admin_forms' => [
        'crash' => CrashConfigForm::class,
        'dice' => DiceConfigForm::class,
        'slot' => SlotConfigForm::class,
    ],

    'launch' => [
        // A launch token must be exchanged (the page opened) within this time, once.
        'token_ttl_seconds' => 120,
        // How long a play session lasts after the page opens.
        'session_ttl_seconds' => 4 * 3600,
        // It ends sooner after this long without a player request (a bet, opening a game, the
        // bet history): an operator's config.session_idle_minutes overrides it, 0 = never.
        'idle_timeout_seconds' => 15 * 60,
    ],

    // The Majestic Games intro the lobby opens with (resources/views/play/provider-intro): it
    // plays while the lobby loads. Games open with their own intros only. `sound`: its chime,
    // where the browser allows sound before a tap and the player hasn't muted a game's sound.
    'provider_intro' => [
        'enabled' => (bool) env('PROVIDER_INTRO_ENABLED', true),
        'sound' => (bool) env('PROVIDER_INTRO_SOUND', true),
    ],

    // Which games' rounds also go out on the legacy `crash` channel (afq's /crash page):
    // a list of codes, or null = every crash-type game (as before there were several).
    // Only the original game: afq's page shows one game, and Orange's /crash page listens
    // on game.{code}, where every round is broadcast.
    'legacy_channel_games' => ['crash'],
];
