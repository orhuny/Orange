<?php

use App\Domain\Game\Models\GameRound;
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
