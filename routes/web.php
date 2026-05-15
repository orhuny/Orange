<?php

use App\Domain\Game\Models\GameRound;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/crash', function () {
    $recentCrashes = GameRound::query()
        ->whereNotNull('crash_point')
        ->latest('id')
        ->limit(12)
        ->pluck('crash_point')
        ->map(fn ($c) => (float) $c)
        ->values()
        ->all();

    return view('crash', ['recentCrashes' => $recentCrashes]);
});
