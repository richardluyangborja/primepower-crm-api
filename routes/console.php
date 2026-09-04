<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:send-due --days=1')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('escalations:evaluate')
    ->dailyAt('06:00')
    ->withoutOverlapping();
