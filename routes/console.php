<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jolpica, with the current season's lap times: laps led come from them, and Led Astray and Clean Sweep wait for them.
Schedule::command('oof:import --recent-laps')
    ->hourly()
    ->withoutOverlapping(720)
    ->appendOutputTo(storage_path('logs/import.log'));

// Session data, fetched again at moments after each session ends (config openf1.refetch_after_minutes).
Schedule::command('oof:import --source=openf1')
    ->hourlyAt(15)
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/import-openf1.log'));

Schedule::command('oof:normalize --laps')
    ->hourlyAt(30)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/normalize.log'));

// After the 04:00 import and the 04:30 normalize.
Schedule::command('oof:dig')
    ->dailyAt('04:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/dig.log'));

// After normalize at :30: sessions with new data, pending awards that have become final,
// and what was decided on an older catalogue version or before a surprise window changed.
Schedule::command('oof:achievements --changed --stale')
    ->hourlyAt(50)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/achievements.log'));

// Approved nickname evolutions take effect on their own (off-season) date.
Schedule::command('oof:nicknames:evolve')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nicknames.log'));
