<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\OrderLine;
use App\Models\SalePayment;
use App\Services\SyncService;
use Illuminate\Console\Command;

class SyncFull extends Command
{
    protected $signature = 'pos:sync-full
                            {--from= : Date de début (YYYY-MM-DD), défaut = tout l\'historique}
                            {--to=   : Date de fin   (YYYY-MM-DD), défaut = aujourd\'hui}
                            {--dry-run : Affiche les compteurs sans envoyer}';

    protected $description = 'Pousse TOUT l\'historique vers le serveur central (même les données déjà synchronisées). '
                            . 'À utiliser lors du premier démarrage, après un remplacement de serveur central, '
                            . 'ou avant d\'éteindre définitivement une machine.';

    private array $tables = [
        'sales'                  => Sale::class,
        'cash_register_sessions' => CashRegisterSession::class,
        'cash_transactions'      => CashTransaction::class,
        'order_lines'            => OrderLine::class,
        'sale_payments'          => SalePayment::class,
    ];

    public function handle(SyncService $syncService): int
    {
        if (empty(config('sync.central_url'))) {
            $this->warn('CENTRAL_SERVER_URL non configuré — sync désactivé.');
            return self::SUCCESS;
        }

        $from   = $this->option('from');
        $to     = $this->option('to');
        $dryRun = $this->option('dry-run');

        $this->info('');
        $this->info('╔══════════════════════════════════════╗');
        $this->info('║  Synchronisation complète (FULL SYNC) ║');
        $this->info('╚══════════════════════════════════════╝');
        $this->info("  Terminal : " . config('sync.terminal_id'));
        $this->info("  Central  : " . config('sync.central_url'));
        $this->info("  Période  : " . ($from ?? 'tout') . ' → ' . ($to ?? 'aujourd\'hui'));
        $this->info('');

        if ($dryRun) {
            $this->warn('  [DRY-RUN] Aucun enregistrement ne sera modifié.');
        }

        // Compter les enregistrements concernés
        $total = 0;
        foreach ($this->tables as $label => $model) {
            $count = $this->buildQuery($model, $from, $to)->count();
            $this->line("  {$label}: <fg=yellow>{$count}</> enregistrements");
            $total += $count;
        }

        $this->info("  Total : <fg=white;options=bold>{$total}</> enregistrements à envoyer");
        $this->info('');

        if ($dryRun) {
            $this->info('[DRY-RUN] Terminé. Aucune modification effectuée.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Réinitialiser synced_at et re-envoyer tous ces enregistrements ?', true)) {
            $this->warn('Annulé.');
            return self::SUCCESS;
        }

        // Réinitialiser synced_at → null pour forcer le renvoi
        $this->info('Réinitialisation de synced_at...');
        foreach ($this->tables as $label => $model) {
            $affected = $this->buildQuery($model, $from, $to)->update(['synced_at' => null]);
            $this->line("  {$label}: {$affected} remis à zéro");
        }

        $this->info('');
        $this->info('Envoi vers le serveur central...');
        $sent = $syncService->sync();
        $this->info("  Envoyés : <fg=green;options=bold>{$sent}</>/{$total}");

        if ($sent < $total) {
            $remaining = $syncService->countPending();
            $this->warn("  {$remaining} enregistrement(s) non envoyés — relancez pos:sync-full ou pos:sync.");
        } else {
            $this->info('  ✓ Synchronisation complète réussie.');
        }

        return self::SUCCESS;
    }

    private function buildQuery(string $model, ?string $from, ?string $to)
    {
        $q = $model::query();
        if ($from) $q->where('created_at', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('created_at', '<=', $to   . ' 23:59:59');
        return $q;
    }
}
