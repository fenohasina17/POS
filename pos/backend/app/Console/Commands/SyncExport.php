<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\OrderLine;
use App\Models\SalePayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncExport extends Command
{
    protected $signature = 'pos:sync-export
                            {--from=  : Date de début YYYY-MM-DD (défaut: tout)}
                            {--to=    : Date de fin   YYYY-MM-DD (défaut: aujourd\'hui)}
                            {--file=  : Exporter dans un fichier JSON au lieu d\'envoyer directement}';

    protected $description = 'Exporte TOUT l\'historique de cette machine vers le central via /api/sync/import-historical. '
                           . 'Utilisez --file pour exporter en JSON (utile si la machine ne peut pas joindre le central directement).';

    public function handle(): int
    {
        $centralUrl   = rtrim(config('sync.central_url', ''), '/');
        $terminalId   = config('sync.terminal_id', 'unknown');
        $restaurantId = config('sync.restaurant_id', 'unknown');
        $apiKey       = config('sync.api_key', '');
        $from         = $this->option('from');
        $to           = $this->option('to');
        $file         = $this->option('file');

        if (!$file && empty($centralUrl)) {
            $this->error('CENTRAL_SERVER_URL non configuré et aucun --file spécifié.');
            return self::FAILURE;
        }

        $this->info("Export historique complet — terminal: {$terminalId}");
        $this->info("Période: " . ($from ?? 'tout') . ' → ' . ($to ?? 'aujourd\'hui'));

        $payload = [
            'terminal_id'   => $terminalId,
            'restaurant_id' => $restaurantId,
            'resources'     => [],
        ];

        // Collecter toutes les données
        $resources = [
            'sales'                  => Sale::class,
            'cash_register_sessions' => CashRegisterSession::class,
            'cash_transactions'      => CashTransaction::class,
            'order_lines'            => OrderLine::class,
            'sale_payments'          => SalePayment::class,
        ];

        $totalRecords = 0;
        foreach ($resources as $resource => $model) {
            $query = $model::query();
            if ($from) $query->where('created_at', '>=', $from . ' 00:00:00');
            if ($to)   $query->where('created_at', '<=', $to   . ' 23:59:59');

            $records = $query->get()->toArray();
            $payload['resources'][$resource] = $records;
            $totalRecords += count($records);
            $this->line("  {$resource}: " . count($records) . " enregistrements");
        }

        $this->info("Total: {$totalRecords} enregistrements");

        // Mode fichier : dump JSON
        if ($file) {
            file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Export JSON sauvegardé dans: {$file}");
            $this->line("Pour importer, envoyez ce fichier au central:");
            $this->line("  curl -X POST {$centralUrl}/api/sync/import-historical \\");
            $this->line("       -H 'Authorization: Bearer <API_KEY>' \\");
            $this->line("       -H 'Content-Type: application/json' \\");
            $this->line("       -d @{$file}");
            return self::SUCCESS;
        }

        // Mode direct : envoi par chunks de 500 pour chaque ressource
        $totalInserted = 0;
        $totalSkipped  = 0;

        $this->info("Envoi vers {$centralUrl}/api/sync/import-historical...");
        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        // Envoyer par ressource en batches pour éviter les timeouts
        foreach ($payload['resources'] as $resource => $records) {
            foreach (array_chunk($records, 200) as $chunk) {
                $chunkPayload = [
                    'terminal_id'   => $terminalId,
                    'restaurant_id' => $restaurantId,
                    'resources'     => [$resource => $chunk],
                ];

                $response = Http::withToken($apiKey)
                    ->timeout(60)
                    ->retry(3, 2000, throw: false)
                    ->post("{$centralUrl}/api/sync/import-historical", $chunkPayload);

                if ($response->successful()) {
                    $data = $response->json();
                    $totalInserted += $data['inserted'] ?? 0;
                    $totalSkipped  += $data['skipped']  ?? 0;
                    $bar->advance(count($chunk));
                } else {
                    $this->newLine();
                    $this->error("Erreur sur {$resource}: " . $response->status() . ' — ' . $response->body());
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Résultat: {$totalInserted} insérés, {$totalSkipped} ignorés (doublons).");

        return self::SUCCESS;
    }
}
