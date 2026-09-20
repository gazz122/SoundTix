<?php

use App\Console\Commands\CompletePastEvents;
use App\Console\Commands\ReleaseExpiredOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ReleaseExpiredOrders::class)->everyMinute()->withoutOverlapping();
Schedule::command(CompletePastEvents::class)->dailyAt('00:05')->withoutOverlapping();
