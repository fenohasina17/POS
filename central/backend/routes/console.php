<?php

use Illuminate\Support\Facades\Schedule;

// Marque offline les terminaux sans heartbeat depuis plus de 2 minutes
Schedule::call(function () {
    \App\Models\Terminal::where('last_heartbeat_at', '<', now()->subMinutes(2))
        ->where('status', 'online')
        ->update(['status' => 'offline']);
})->everyMinute();
