<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Owed credits and rollbacks whose callback failed are re-sent until the operator
// acknowledges them. Each row is claimed atomically, so an overlap is harmless; the
// short mutex expiry only keeps a killed run from blocking the next ones for a day.
// In the background so a slow operator never delays the rest of the schedule.
Schedule::command('wallet:reconcile')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();

// Instant bets a dying process left pending: settled if the stake was taken, else rolled back.
Schedule::command('games:settle-stale-bets')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground();
