<?php

use App\Domain\Game\Enums\RoundState;
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
        ->get(['round_uuid', 'crash_point'])
        // Each chip opens its round's details (GET /v1/rounds/{uuid}).
        ->map(fn (GameRound $round) => ['round_uuid' => $round->round_uuid, 'crash_point' => (float) $round->crash_point])
        ->values()
        ->all();

    // The live bet list starts from these: the round in play (if any) and the last one over.
    $liveRounds = config('crash.live_bets', true) ? [
        'current' => GameRound::query()->where('state', '!=', RoundState::Settled)->latest('id')->value('round_uuid'),
        'previous' => GameRound::query()->where('state', RoundState::Settled)->latest('id')->value('round_uuid'),
    ] : null;

    return view('crash', ['recentCrashes' => $recentCrashes, 'liveRounds' => $liveRounds]);
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
