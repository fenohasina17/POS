<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Models\RemoteSale;
use App\Models\RemoteCashRegisterSession;
use App\Models\RemoteCashTransaction;
use App\Models\RemoteOrderLine;
use App\Models\RemoteSalePayment;
use App\Events\TerminalDataReceived;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class SyncController extends Controller
{
    // Map resource name → modèle + colonnes à extraire
    private const RESOURCE_MAP = [
        'sales' => [
            'model'  => RemoteSale::class,
            'fields' => [
                'id'                   => 'remote_id',
                'sale_number'          => 'sale_number',
                'ticket_number'        => 'ticket_number',
                'status'               => 'status',
                'total_amount'         => 'total_amount',
                'final_amount'         => 'final_amount',
                'discount_percentage'  => 'discount_percentage',
                'amount_received'      => 'amount_received',
                'change_amount'        => 'change_amount',
                'user_id'              => 'user_id_remote',
                'seller_name'          => 'seller_name',
                'point_of_sale_id'     => 'point_of_sale_id_remote',
                'point_of_sale_name'   => 'point_of_sale_name',
                'cash_register_session_id' => 'session_id_remote',
                'table_id'             => 'table_id_remote',
                'created_at'           => 'remote_created_at',
                'completed_at'         => 'remote_completed_at',
            ],
        ],
        'cash_register_sessions' => [
            'model'  => RemoteCashRegisterSession::class,
            'fields' => [
                'id'                    => 'remote_id',
                'starting_amount'       => 'starting_amount',
                'actual_cash_amount'    => 'actual_cash_amount',
                'expected_cash_amount'  => 'expected_cash_amount',
                'total_sales'           => 'total_sales',
                'total_refunds'         => 'total_refunds',
                'is_closed'             => 'is_closed',
                'has_discrepancy'       => 'has_discrepancy',
                'user_id'               => 'user_id_remote',
                'opened_at'             => 'remote_opened_at',
                'closed_at'             => 'remote_closed_at',
            ],
        ],
        'cash_transactions' => [
            'model'  => RemoteCashTransaction::class,
            'fields' => [
                'id'         => 'remote_id',
                'type'       => 'type',
                'amount'     => 'amount',
                'label'      => 'label',
                'session_id' => 'session_id_remote',
                'created_at' => 'remote_created_at',
            ],
        ],
        'order_lines' => [
            'model'  => RemoteOrderLine::class,
            'fields' => [
                'id'            => 'remote_id',
                'sale_id'       => 'sale_id_remote',
                'product_id'    => 'product_id_remote',
                'product_name'  => 'product_name',
                'category_name' => 'category_name',
                'quantity'      => 'quantity',
                'price'         => 'unit_price',
                'total'         => 'total',
                'created_at'    => 'remote_created_at',
            ],
        ],
        'sale_payments' => [
            'model'  => RemoteSalePayment::class,
            'fields' => [
                'id'         => 'remote_id',
                'sale_id'    => 'sale_id_remote',
                'amount'     => 'amount',
                'created_at' => 'remote_created_at',
            ],
        ],
    ];

    /**
     * Reçoit un batch de données depuis un terminal POS.
     * POST /api/sync/receive
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_id'   => 'required|string|max:100',
            'restaurant_id' => 'required|string|max:100',
            'resource'      => 'required|string|in:' . implode(',', array_keys(self::RESOURCE_MAP)),
            'records'       => 'required|array|min:1|max:500',
            'sent_at'       => 'required|string',
        ]);

        $terminalId   = $validated['terminal_id'];
        $restaurantId = $validated['restaurant_id'];
        $resource     = $validated['resource'];
        $records      = $validated['records'];

        $config  = self::RESOURCE_MAP[$resource];
        $model   = $config['model'];
        $fields  = $config['fields'];

        $inserted = 0;
        $skipped  = 0;

        DB::transaction(function () use (
            $model, $fields, $records, $terminalId, $restaurantId, &$inserted, &$skipped
        ) {
            foreach ($records as $record) {
                $row = [
                    'terminal_id'   => $terminalId,
                    'restaurant_id' => $restaurantId,
                    'received_at'   => now(),
                ];

                foreach ($fields as $sourceKey => $destKey) {
                    $row[$destKey] = $record[$sourceKey] ?? null;
                }

                // Upsert pour enrichir les noms dénormalisés sur les lignes existantes
                if ($model === RemoteOrderLine::class) {
                    $affected = $model::upsert(
                        [$row],
                        ['terminal_id', 'remote_id'],
                        ['product_name', 'category_name']
                    );
                } elseif ($model === RemoteSale::class) {
                    $affected = $model::upsert(
                        [$row],
                        ['terminal_id', 'remote_id'],
                        ['seller_name', 'point_of_sale_name']
                    );
                } else {
                    $affected = $model::insertOrIgnore([$row]);
                }
                $affected ? $inserted++ : $skipped++;
            }
        });

        // Mise à jour du statut du terminal
        Terminal::updateOrCreate(
            ['terminal_id' => $terminalId],
            [
                'restaurant_id' => $restaurantId,
                'last_sync_at'  => now(),
                'status'        => 'online',
            ]
        );

        // Broadcast WebSocket vers le dashboard (non bloquant — sync ne doit jamais échouer à cause du broadcast)
        try {
            event(new TerminalDataReceived($terminalId, $restaurantId, $resource, $inserted));
        } catch (\Throwable $e) {
            Log::warning('SyncController: broadcast échoué (non fatal)', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ]);
    }

    /**
     * Import historique complet depuis un terminal (même éteint via export manuel).
     * Accepte plusieurs ressources en une seule requête.
     * POST /api/sync/import-historical
     *
     * Body: {
     *   terminal_id, restaurant_id,
     *   resources: { sales: [...], cash_register_sessions: [...], order_lines: [...], sale_payments: [...] }
     * }
     */
    public function importHistorical(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_id'   => 'required|string|max:100',
            'restaurant_id' => 'required|string|max:100',
            'resources'     => 'required|array',
        ]);

        $terminalId   = $validated['terminal_id'];
        $restaurantId = $validated['restaurant_id'];
        $resources    = $validated['resources'];

        $totalInserted = 0;
        $totalSkipped  = 0;
        $errors        = [];

        foreach ($resources as $resource => $records) {
            if (!isset(self::RESOURCE_MAP[$resource])) {
                $errors[] = "Ressource inconnue: {$resource}";
                continue;
            }
            if (!is_array($records) || empty($records)) continue;

            $config = self::RESOURCE_MAP[$resource];
            $model  = $config['model'];
            $fields = $config['fields'];

            // Traitement par chunks de 500 pour éviter les timeouts mémoire
            foreach (array_chunk($records, 500) as $chunk) {
                DB::transaction(function () use ($model, $fields, $chunk, $terminalId, $restaurantId, &$totalInserted, &$totalSkipped) {
                    foreach ($chunk as $record) {
                        $row = [
                            'terminal_id'   => $terminalId,
                            'restaurant_id' => $restaurantId,
                            'received_at'   => now(),
                        ];
                        foreach ($fields as $sourceKey => $destKey) {
                            $row[$destKey] = $record[$sourceKey] ?? null;
                        }
                        $model::insertOrIgnore([$row]) ? $totalInserted++ : $totalSkipped++;
                    }
                });
            }
        }

        Terminal::updateOrCreate(
            ['terminal_id' => $terminalId],
            ['restaurant_id' => $restaurantId, 'last_sync_at' => now()]
        );

        return response()->json([
            'inserted' => $totalInserted,
            'skipped'  => $totalSkipped,
            'errors'   => $errors,
        ]);
    }

    /**
     * Reçoit le heartbeat d'un terminal.
     * POST /api/sync/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'terminal_id'    => 'required|string|max:100',
            'restaurant_id'  => 'required|string|max:100',
            'app_version'    => 'nullable|string|max:50',
            'pending_sync'   => 'nullable|integer|min:0',
            'last_sync_at'   => 'nullable|string',
            'sent_at'        => 'required|string',
        ]);

        $terminal = Terminal::updateOrCreate(
            ['terminal_id' => $validated['terminal_id']],
            [
                'restaurant_id'      => $validated['restaurant_id'],
                'app_version'        => $validated['app_version'] ?? null,
                'status'             => 'online',
                'ip_address'         => $request->ip(),
                'pending_sync_count' => $validated['pending_sync'] ?? 0,
                'last_heartbeat_at'  => now(),
                'last_sync_at'       => isset($validated['last_sync_at'])
                    ? Carbon::parse($validated['last_sync_at'])
                    : null,
            ]
        );

        // Broadcast mise à jour terminal vers le dashboard (non bloquant)
        try {
            event(new TerminalDataReceived(
                $terminal->terminal_id,
                $terminal->restaurant_id,
                'heartbeat',
                0
            ));
        } catch (\Throwable $e) {
            Log::warning('SyncController: heartbeat broadcast échoué (non fatal)', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }
}
