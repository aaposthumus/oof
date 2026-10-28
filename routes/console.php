<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('oof:import')
    ->hourly()
    ->withoutOverlapping(720)
    ->appendOutputTo(storage_path('logs/import.log'));

// Session data, fetched again at moments after each session ends (config openf1.refetch_after_minutes).
Schedule::command('oof:import --source=openf1')
    ->hourlyAt(15)
    ->withoutOverlapping(240)
    ->appendOutputTo(storage_path('logs/import-openf1.log'));

Schedule::command('oof:normalize')
    ->hourlyAt(30)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/normalize.log'));

// After the 04:00 import and the 04:30 normalize.
Schedule::command('oof:dig')
    ->dailyAt('04:50')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/dig.log'));
