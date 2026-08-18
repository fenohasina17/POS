<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncNow extends Command
{
    protected $signature   = 'pos:sync';
    protected $description = 'Synchronise immédiatement les données non-envoyées vers le serveur central';

    public function handle(SyncService $syncService): int
    {
        if (empty(config('sync.central_url'))) {
            $this->warn('CENTRAL_SERVER_URL non configuré — sync désactivé.');
            return self::SUCCESS;
        }

        $this->info('Synchronisation en cours...');
        $pending = $syncService->countPending();
        $this->line("  Enregistrements en attente : {$pending}");

        $sent = $syncService->sync();
        $this->info("  Envoyés : {$sent}");

        return self::SUCCESS;
    }
}
