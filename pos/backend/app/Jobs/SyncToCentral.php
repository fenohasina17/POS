<?php

namespace App\Jobs;

use App\Services\SyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncToCentral implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 3 tentatives maximum avant de considérer le job comme échoué
    public int $tries = 3;

    // Délai entre les tentatives : 30s, 60s, 120s (backoff exponentiel)
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    public function handle(SyncService $syncService): void
    {
        $syncService->sync();
    }
}
