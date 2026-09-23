<?php

namespace App\Console\Commands;

use App\Events\ProductCatalogUpdated;
use App\Services\CatalogSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCatalog extends Command
{
    protected $signature   = 'pos:sync-catalog';
    protected $description = 'Récupère le catalogue produits depuis le serveur central';

    public function handle(CatalogSyncService $catalogSyncService): int
    {
        if (empty(config('sync.central_url'))) {
            $this->warn('CENTRAL_SERVER_URL non configuré — sync catalogue désactivé.');
            return self::SUCCESS;
        }

        $this->info('Synchronisation du catalogue en cours...');
        $result = $catalogSyncService->pull();

        $created = count($result['created']);
        $updated = count($result['updated']);
        $this->line("  Créés : {$created} — Modifiés : {$updated}");

        if ($created > 0 || $updated > 0) {
            try {
                event(new ProductCatalogUpdated($result['created'], $result['updated']));
            } catch (\Throwable $e) {
                // Le catalogue est déjà à jour en base à ce stade — un souci de
                // notification temps réel ne doit pas faire échouer la commande.
                Log::warning('SyncCatalog: échec de la notification temps réel', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
