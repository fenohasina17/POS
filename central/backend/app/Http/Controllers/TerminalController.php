<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Terminal;
use App\Models\RemoteSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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

    /**
     * Enregistre manuellement un terminal et génère sa clé API.
     * POST /api/terminals
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'terminal_id'   => 'required|string|unique:terminals,terminal_id',
            'restaurant_id' => 'required|string',
            'region'        => 'nullable|string|max:100',
        ]);

        $apiKey = Str::random(48);

        $terminal = Terminal::create([
            'terminal_id'   => $data['terminal_id'],
            'restaurant_id' => $data['restaurant_id'],
            'region'        => $data['region'] ?? null,
            'api_key_hash'  => hash('sha256', $apiKey),
            'status'        => 'offline',
        ]);

        return response()->json([
            ...$this->formatTerminal($terminal),
            'api_key' => $apiKey, // affiché une seule fois
        ], 201);
    }

    /**
     * Révoque et regénère la clé API d'un terminal.
     * POST /api/terminals/{terminal}/rotate-key
     */
    public function rotateKey(string $terminalId): JsonResponse
    {
        $terminal = Terminal::where('terminal_id', $terminalId)->firstOrFail();
        $apiKey   = Str::random(48);
        $terminal->update(['api_key_hash' => hash('sha256', $apiKey)]);

        return response()->json([
            'terminal_id' => $terminal->terminal_id,
            'api_key'     => $apiKey,
        ]);
    }

    /**
     * Supprime un terminal et ses alertes actives.
     * DELETE /api/terminals/{terminal}
     */
    public function destroy(string $terminalId): JsonResponse
    {
        $terminal = Terminal::where('terminal_id', $terminalId)->firstOrFail();
        Alert::where('terminal_id', $terminalId)->delete();
        $terminal->delete();

        return response()->json(['message' => 'Terminal supprimé.']);
    }

    private function formatTerminal(Terminal $terminal): array
    {
        return [
            'terminal_id'        => $terminal->terminal_id,
            'restaurant_id'      => $terminal->restaurant_id,
            'region'             => $terminal->region,
            'app_version'        => $terminal->app_version,
            'status'             => $terminal->computed_status,
            'ip_address'         => $terminal->ip_address,
            'pending_sync_count' => $terminal->pending_sync_count,
            'last_heartbeat_at'  => $terminal->last_heartbeat_at,
            'last_sync_at'       => $terminal->last_sync_at,
        ];
    }
}
