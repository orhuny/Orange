<?php

use App\Http\Controllers\CrashPageController;
use App\Http\Controllers\GamePageController;
use App\Http\Controllers\LobbyPageController;
use App\Http\Controllers\PlayPageController;
use App\Http\Middleware\AllowGameEmbed;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The page every crash-type game plays in (?game={code}); see CrashPageController.
Route::get('/crash', CrashPageController::class)->middleware(AllowGameEmbed::class);

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
