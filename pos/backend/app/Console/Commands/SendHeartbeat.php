<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SendHeartbeat extends Command
{
    protected $signature   = 'pos:heartbeat';
    protected $description = 'Envoie le statut du terminal au serveur central (online, version, pending sync)';

    public function handle(SyncService $syncService): int
    {
        if (empty(config('sync.central_url'))) {
            return self::SUCCESS;
        }

        $syncService->sendHeartbeat();
        return self::SUCCESS;
    }
}
