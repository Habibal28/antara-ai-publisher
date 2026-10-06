<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\RunScheduledCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue each workflow command; every command handles at most one article per run.
Schedule::job(new RunScheduledCommand('ai:extract-facts'), 'scheduled')->everyMinute();
Schedule::job(new RunScheduledCommand('ai:rewrite'), 'scheduled')->everyMinute();
Schedule::job(new RunScheduledCommand('telegram:send-draft'), 'scheduled')->everyMinute();
Schedule::job(new RunScheduledCommand('wordpress:publish'), 'scheduled')->everyMinute();
Schedule::job(new RunScheduledCommand('antara:import'), 'scheduled')->cron('0 */4 * * *')->timezone('Asia/Jakarta');
