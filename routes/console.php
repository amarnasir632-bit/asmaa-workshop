<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('workshop:backup --frequency=daily')->dailyAt('02:00')->timezone(config('app.display_timezone'))->withoutOverlapping();
Schedule::command('workshop:backup --frequency=weekly')->weeklyOn(0, '02:15')->timezone(config('app.display_timezone'))->withoutOverlapping();
Schedule::command('workshop:backup --frequency=monthly')->monthlyOn(1, '02:30')->timezone(config('app.display_timezone'))->withoutOverlapping();
Schedule::command('workshop:backup-prune')->dailyAt('03:00')->timezone(config('app.display_timezone'))->withoutOverlapping();
