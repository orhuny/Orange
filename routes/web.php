<?php

use App\Domain\Game\Models\GameRound;
use App\Http\Controllers\GamePageController;
use App\Http\Controllers\LobbyPageController;
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

// Game pages (resources/views/games/{type}.blade.php), embedded by Orange's host pages
// (direct play) or by an operator page (relay play).
Route::get('/games/{type}', GamePageController::class)
    ->where('type', '[a-z0-9_-]+')
    ->middleware(AllowGameEmbed::class);

// The lobby a lobby launch token opens; games open inside it.
Route::get('/lobby', LobbyPageController::class)->middleware(AllowGameEmbed::class);

// Direct play: the page a launch token opens (see LaunchController). Hosts the game frame.
Route::get('/play/{code}', PlayPageController::class)
    ->where('code', '[a-z0-9_-]+')
    ->middleware(AllowGameEmbed::class);
