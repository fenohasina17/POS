<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\CashRegisterSession;
use App\Models\CashTransaction;
use App\Models\OrderLine;
use App\Models\SalePayment;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class SyncService
{
    // Nombre maximum d'enregistrements envoyés par batch
    // Volontairement petit pour éviter les timeouts et passer dans n'importe quelle connexion
    private const BATCH_SIZE = 50;

    private string $centralUrl;
    private string $terminalId;
    private string $restaurantId;
    private string $apiKey;

    public function __construct()
    {
        $this->centralUrl   = rtrim(config('sync.central_url', ''), '/');
        $this->terminalId   = config('sync.terminal_id', 'unknown');
        $this->restaurantId = config('sync.restaurant_id', 'unknown');
        $this->apiKey       = config('sync.api_key', '');
    }

    /**
     * Point d'entrée principal — appelé par le job SyncToCentral.
     * Retourne le nombre total d'enregistrements envoyés.
     */
    public function sync(): int
    {
        if (empty($this->centralUrl)) {
            return 0;
        }

        $log = SyncLog::create([
            'status'       => 'pending',
            'records_sent' => 0,
            'started_at'   => now(),
        ]);

        $totalSent   = 0;
        $totalFailed = 0;

        try {
            $totalSent += $this->syncSales();
            $totalSent += $this->syncModel(CashRegisterSession::query(), 'cash_register_sessions');
            $totalSent += $this->syncModel(CashTransaction::query(), 'cash_transactions');
            $totalSent += $this->syncOrderLines();
            $totalSent += $this->syncModel(SalePayment::query(), 'sale_payments');

            $log->update([
                'status'       => 'success',
                'records_sent' => $totalSent,
                'finished_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'records_sent'  => $totalSent,
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            Log::error('SyncService: erreur lors de la synchronisation', [
                'error' => $e->getMessage(),
            ]);
        }

        return $totalSent;
    }

    /**
     * Synchronise les ventes en dénormalisant seller_name et point_of_sale_name.
     */
    private function syncSales(): int
    {
        $sent = 0;

        Sale::with(['user:id,name', 'pointOfSale:id,name'])
            ->whereNull('synced_at')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($records) use (&$sent) {
                $rows = $records->map(function ($sale) {
                    $arr = $sale->toArray();
                    $arr['seller_name']       = $sale->user?->name;
                    $arr['point_of_sale_name'] = $sale->pointOfSale?->name;
                    return $arr;
                })->all();

                $payload = [
                    'terminal_id'   => $this->terminalId,
                    'restaurant_id' => $this->restaurantId,
                    'resource'      => 'sales',
                    'records'       => $rows,
                    'sent_at'       => now()->toIso8601String(),
                ];

                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->retry(3, 1000, throw: false)
                    ->post("{$this->centralUrl}/api/sync/receive", $payload);

                if ($response->successful()) {
                    $ids = $records->pluck('id')->all();
                    Sale::whereIn('id', $ids)->update(['synced_at' => now()]);
                    $sent += count($ids);
                } else {
                    Log::warning('SyncService: échec batch sales', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    if ($response->status() >= 500 || $response->status() === 0) {
                        return false;
                    }
                }
            });

        return $sent;
    }

    /**
     * Synchronise les order_lines en enrichissant chaque ligne avec product_name et category_name.
     * Dénormalisation intentionnelle : le Central n'a pas accès au catalogue POS.
     */
    private function syncOrderLines(): int
    {
        $sent = 0;

        OrderLine::with(['product.category'])
            ->whereNull('synced_at')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($records) use (&$sent) {
                $rows = $records->map(function ($line) {
                    $arr = $line->toArray();
                    $arr['product_name']   = $line->product?->name;
                    $arr['category_name']  = $line->product?->category?->name;
                    return $arr;
                })->all();

                $payload = [
                    'terminal_id'   => $this->terminalId,
                    'restaurant_id' => $this->restaurantId,
                    'resource'      => 'order_lines',
                    'records'       => $rows,
                    'sent_at'       => now()->toIso8601String(),
                ];

                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->retry(3, 1000, throw: false)
                    ->post("{$this->centralUrl}/api/sync/receive", $payload);

                if ($response->successful()) {
                    $ids = $records->pluck('id')->all();
                    OrderLine::whereIn('id', $ids)->update(['synced_at' => now()]);
                    $sent += count($ids);
                } else {
                    Log::warning('SyncService: échec batch order_lines', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    if ($response->status() >= 500 || $response->status() === 0) {
                        return false;
                    }
                }
            });

        return $sent;
    }

    /**
     * Synchronise les enregistrements non encore envoyés d'un modèle donné.
     */
    private function syncModel($query, string $resource): int
    {
        $sent = 0;

        $query->whereNull('synced_at')
            ->orderBy('id')
            ->chunk(self::BATCH_SIZE, function ($records) use ($resource, &$sent) {
                $payload = [
                    'terminal_id'   => $this->terminalId,
                    'restaurant_id' => $this->restaurantId,
                    'resource'      => $resource,
                    'records'       => $records->toArray(),
                    'sent_at'       => now()->toIso8601String(),
                ];

                $response = Http::withToken($this->apiKey)
                    ->timeout(30)
                    ->retry(3, 1000, throw: false)
                    ->post("{$this->centralUrl}/api/sync/receive", $payload);

                if ($response->successful()) {
                    // Marquer tous les enregistrements du batch comme synchronisés
                    $ids = $records->pluck('id')->all();
                    $records->first()->getModel()::whereIn('id', $ids)->update(['synced_at' => now()]);
                    $sent += count($ids);
                } else {
                    Log::warning("SyncService: échec batch {$resource}", [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    // Arrêter ce chunk si le serveur est indisponible (503, réseau)
                    if ($response->status() >= 500 || $response->status() === 0) {
                        return false; // stoppe le chunk() Laravel
                    }
                }
            });

        return $sent;
    }

    /**
     * Envoie un heartbeat au serveur central avec l'état du terminal.
     */
    public function sendHeartbeat(): void
    {
        if (empty($this->centralUrl)) {
            return;
        }

        $pendingCount = $this->countPending();

        Http::withToken($this->apiKey)
            ->timeout(5)
            ->post("{$this->centralUrl}/api/sync/heartbeat", [
                'terminal_id'    => $this->terminalId,
                'restaurant_id'  => $this->restaurantId,
                'app_version'    => config('app.version', '1.0.0'),
                'status'         => 'online',
                'pending_sync'   => $pendingCount,
                'last_sync_at'   => SyncLog::where('status', 'success')->latest('started_at')->value('started_at'),
                'sent_at'        => now()->toIso8601String(),
            ]);
    }

    /**
     * Compte le total des enregistrements en attente de synchronisation.
     */
    public function countPending(): int
    {
        return Sale::whereNull('synced_at')->count()
            + CashRegisterSession::whereNull('synced_at')->count()
            + CashTransaction::whereNull('synced_at')->count()
            + OrderLine::whereNull('synced_at')->count()
            + SalePayment::whereNull('synced_at')->count();
    }
}
