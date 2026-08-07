<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Models\RemoteSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TerminalController extends Controller
{
    /**
     * Liste tous les terminaux avec leur statut calculé.
     * GET /api/terminals
     */
    public function index(Request $request): JsonResponse
    {
        $terminals = Terminal::query()
            ->when($request->restaurant_id, fn($q, $r) => $q->where('restaurant_id', $r))
            ->orderBy('restaurant_id')
            ->orderBy('terminal_id')
            ->get()
            ->map(fn($t) => $this->formatTerminal($t));

        return response()->json($terminals);
    }

    /**
     * Détail d'un terminal + statistiques récentes.
     * GET /api/terminals/{terminalId}
     */
    public function show(string $terminalId): JsonResponse
    {
        $terminal = Terminal::where('terminal_id', $terminalId)->firstOrFail();

        $today = now()->startOfDay();

        $stats = [
            'sales_today'   => RemoteSale::where('terminal_id', $terminalId)
                ->where('remote_created_at', '>=', $today)
                ->count(),
            'revenue_today' => RemoteSale::where('terminal_id', $terminalId)
                ->where('status', 'completed')
                ->where('remote_created_at', '>=', $today)
                ->sum('final_amount'),
        ];

        return response()->json([
            ...$this->formatTerminal($terminal),
            'stats' => $stats,
        ]);
    }

    private function formatTerminal(Terminal $terminal): array
    {
        return [
            'terminal_id'        => $terminal->terminal_id,
            'restaurant_id'      => $terminal->restaurant_id,
            'app_version'        => $terminal->app_version,
            'status'             => $terminal->computed_status,
            'ip_address'         => $terminal->ip_address,
            'pending_sync_count' => $terminal->pending_sync_count,
            'last_heartbeat_at'  => $terminal->last_heartbeat_at,
            'last_sync_at'       => $terminal->last_sync_at,
        ];
    }
}
