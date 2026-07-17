<?php

use App\Console\Commands\ExpirePendingPaymentsCommand;
use App\Console\Commands\SchedulerHeartbeatCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('phoenix:sync', function () {
    /** @var \App\Services\Sync\SyncFromPhoenixService $sync */
    $sync = app(\App\Services\Sync\SyncFromPhoenixService::class);

    $result = $sync->syncAll();

    $this->info('Phoenix sync completed.');
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Sync products and stock from Phoenix (mock/real)');

Schedule::command(ExpirePendingPaymentsCommand::class)->everyFiveMinutes();
Schedule::command(SchedulerHeartbeatCommand::class)->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
