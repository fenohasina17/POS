<?php

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncShutdown extends Command
{
    protected $signature   = 'pos:sync-shutdown';
    protected $description = 'Sync d\'urgence avant extinction : pousse toutes les données non-envoyées. '
                           . 'Appelé automatiquement par le signal SIGTERM Docker (STOPSIGNAL).';

    public function handle(SyncService $syncService): int
    {
        if (empty(config('sync.central_url'))) {
            return self::SUCCESS;
        }

        $pending = $syncService->countPending();

        if ($pending === 0) {
            $this->info('[shutdown] Aucune donnée en attente.');
            return self::SUCCESS;
        }

        $this->warn("[shutdown] {$pending} enregistrement(s) non syncronisés — envoi en cours...");

        $maxAttempts = 5;
        $totalSent   = 0;

        for ($i = 1; $i <= $maxAttempts; $i++) {
            $sent = $syncService->sync();
            $totalSent += $sent;
            $remaining  = $syncService->countPending();

            $this->line("[shutdown] Tentative {$i}/{$maxAttempts} : {$sent} envoyés, {$remaining} restants");

            if ($remaining === 0) break;

            sleep(2);
        }

        $stillPending = $syncService->countPending();

        if ($stillPending > 0) {
            $this->error("[shutdown] ATTENTION : {$stillPending} enregistrement(s) non synchronisés avant extinction.");
        } else {
            $this->info("[shutdown] Toutes les données synchronisées avec succès.");
        }

        return self::SUCCESS;
    }
}
