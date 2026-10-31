<?php

use App\Domain\Game\Engines\EngineRegistry;
use App\Domain\Game\Models\Game;
use App\Domain\Game\Models\GameRound;
use App\Domain\Operator\Models\Operator;
use App\Http\Controllers\PlayPageController;
use App\Http\Middleware\AllowGameEmbed;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/crash', function () {
    // Only finished rounds: crash_point is stored at round start, so the live
    // round must never show up here.
    $recentCrashes = GameRound::query()
        ->whereIn('state', GameRound::CRASH_POINT_PUBLIC_STATES)
        ->whereNotNull('crash_point')
        ->latest('id')
        ->limit(8)
        ->pluck('crash_point')
        ->map(fn ($c) => (float) $c)
        ->values()
        ->all();

    return view('crash', ['recentCrashes' => $recentCrashes]);
})->middleware(AllowGameEmbed::class);

// Dice. Embedded by Orange's host pages (direct play) or by an operator page (relay play).
Route::get('/games/dice', function () {
    $game = Game::query()->where('type', 'dice')->where('status', '!=', 'draft')->orderBy('id')->first();
    $config = $game instanceof Game ? app(EngineRegistry::class)->for('dice')->validateConfig($game, (array) $game->config) : [];

    return view('games.dice', [
        'rtp' => $game instanceof Game ? number_format((float) $game->rtp_target, 4, '.', '') : '0.9900',
        'minChance' => $config['min_chance'] ?? '0.01',
        'maxChance' => $config['max_chance'] ?? '98.00',
        'allowedOrigins' => array_values(array_unique(array_merge((array) config('embed.allowed_origins', []), Operator::allEmbedOrigins()))),
    ]);
})->middleware(AllowGameEmbed::class);

// Direct play: the page a launch token opens (see LaunchController). Hosts the game frame.
Route::get('/play/{code}', PlayPageController::class)
    ->where('code', '[a-z0-9_-]+')
    ->middleware(AllowGameEmbed::class);
