<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:clean-orphan-table-locks')->everyMinute();

// Synchronisation vers le serveur central (inactif si CENTRAL_SERVER_URL est vide)
Schedule::command('pos:sync')->everyThirtySeconds();
Schedule::command('pos:heartbeat')->everyMinute();
