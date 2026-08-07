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
                'point_of_sale_id'     => 'point_of_sale_id_remote',
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
                'id'         => 'remote_id',
                'sale_id'    => 'sale_id_remote',
                'product_id' => 'product_id_remote',
                'quantity'   => 'quantity',
                'price'      => 'unit_price',
                'total'      => 'total',
                'created_at' => 'remote_created_at',
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

                // insertOrIgnore garantit l'idempotence via la contrainte unique (terminal_id, remote_id)
                $affected = $model::insertOrIgnore([$row]);
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

        // Broadcast WebSocket vers le dashboard
        event(new TerminalDataReceived($terminalId, $restaurantId, $resource, $inserted));

        return response()->json([
            'inserted' => $inserted,
            'skipped'  => $skipped,
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

        // Broadcast mise à jour terminal vers le dashboard
        event(new TerminalDataReceived(
            $terminal->terminal_id,
            $terminal->restaurant_id,
            'heartbeat',
            0
        ));

        return response()->json(['status' => 'ok']);
    }
}
