<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('sieg.schedule.enabled')) {
    Schedule::command('sieg:sync')
        ->cron((string) config('sieg.schedule.cron'))
        ->withoutOverlapping(30)
        ->onOneServer()
        ->description('Sincroniza XMLs do SIEG');
}
