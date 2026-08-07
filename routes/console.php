<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('posts:archive-expired')->everyMinute()->withoutOverlapping();
Schedule::command('newsletter:send-digest')->weeklyOn(1, '08:00');
