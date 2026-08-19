<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Models\RemoteSale;
use App\Models\RemoteOrderLine;
use App\Models\RemoteCashRegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id;
        $terminalId   = $request->terminal_id;
        $dateFrom     = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : now()->startOfDay();
        $dateTo       = $request->filled('date_to')   ? Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $prevFrom     = $dateFrom->copy()->subDay();
        $prevTo       = $dateTo->copy()->subDay();

        // ── Terminaux ───────────────────────────────────────────────────────
        $terminals    = Terminal::when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
                                ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
                                ->get();
        $onlineCount  = $terminals->filter->is_online->count();
        $pendingTotal = $terminals->sum('pending_sync_count');

        // ── Ventes de base ───────────────────────────────────────────────────
        $base = RemoteSale::where('status', 'completed')
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId));

        $revenueToday    = (clone $base)->whereBetween('remote_created_at', [$dateFrom, $dateTo])->sum('final_amount');
        $salesCountToday = (clone $base)->whereBetween('remote_created_at', [$dateFrom, $dateTo])->count();
        $revenuePrev     = (clone $base)->whereBetween('remote_created_at', [$prevFrom, $prevTo])->sum('final_amount');
        $avgTicket       = $salesCountToday > 0 ? round($revenueToday / $salesCountToday, 2) : 0;
        $avgTicketPrev   = (clone $base)->whereBetween('remote_created_at', [$prevFrom, $prevTo])->avg('final_amount') ?? 0;

        // ── CA par restaurant ────────────────────────────────────────────────
        $byRestaurant = RemoteSale::select('restaurant_id',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
            ->groupBy('restaurant_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // ── Courbe 7 jours (ou période) ─────────────────────────────────────
        $chartDays  = min(90, $dateFrom->diffInDays($dateTo) + 1);
        $salesByDay = RemoteSale::select(
                DB::raw("DATE(remote_created_at) as date"),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', now()->subDays($chartDays))
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ── Ventes mensuelles (12 mois) ──────────────────────────────────────
        $monthlySales = RemoteSale::select(
                DB::raw("TO_CHAR(remote_created_at, 'YYYY-MM') as month"),
                DB::raw('SUM(final_amount) as revenue'),
                DB::raw('COUNT(*) as sales_count')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // ── Heatmap horaire (7 derniers jours × 6 créneaux) ─────────────────
        $heatmapRaw = RemoteSale::select(
                DB::raw("DATE(remote_created_at) as day"),
                DB::raw("EXTRACT(HOUR FROM remote_created_at)::int as hour"),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', now()->subDays(7)->startOfDay())
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
            ->groupBy('day', 'hour')
            ->orderBy('day')
            ->orderBy('hour')
            ->get();

        // Créneaux horaires (6 blocs de 4h)
        $slots = [
            ['key' => '0-4',   'label' => '00h–04h', 'hours' => range(0, 3)],
            ['key' => '4-8',   'label' => '04h–08h', 'hours' => range(4, 7)],
            ['key' => '8-12',  'label' => '08h–12h', 'hours' => range(8, 11)],
            ['key' => '12-16', 'label' => '12h–16h', 'hours' => range(12, 15)],
            ['key' => '16-20', 'label' => '16h–20h', 'hours' => range(16, 19)],
            ['key' => '20-24', 'label' => '20h–00h', 'hours' => range(20, 23)],
        ];

        $days7    = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->toDateString());
        $heatmap  = $days7->map(function ($day) use ($heatmapRaw, $slots) {
            return [
                'day'   => $day,
                'slots' => collect($slots)->map(function ($slot) use ($day, $heatmapRaw) {
                    $cells = $heatmapRaw->where('day', $day)->whereIn('hour', $slot['hours']);
                    return [
                        'key'     => $slot['key'],
                        'label'   => $slot['label'],
                        'revenue' => round($cells->sum('revenue'), 2),
                        'count'   => $cells->sum('sales_count'),
                    ];
                })->values(),
            ];
        });

        // ── Top produits ─────────────────────────────────────────────────────
        $topProducts = RemoteOrderLine::select('product_name',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->whereNotNull('product_name')
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->when($terminalId,   fn($q) => $q->where('terminal_id',   $terminalId))
            ->where('remote_created_at', '>=', $dateFrom)
            ->where('remote_created_at', '<=', $dateTo)
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'terminals' => [
                'total'        => $terminals->count(),
                'online'       => $onlineCount,
                'offline'      => $terminals->count() - $onlineCount,
                'pending_sync' => $pendingTotal,
            ],
            'sales_today' => [
                'count'             => $salesCountToday,
                'revenue'           => round($revenueToday, 2),
                'revenue_yesterday' => round($revenuePrev, 2),
                'avg_ticket'        => $avgTicket,
                'avg_ticket_prev'   => round($avgTicketPrev, 2),
                'evolution_pct'     => $revenuePrev > 0
                    ? round((($revenueToday - $revenuePrev) / $revenuePrev) * 100, 1)
                    : null,
            ],
            'filters' => [
                'date_from'     => $dateFrom->toDateString(),
                'date_to'       => $dateTo->toDateString(),
                'restaurant_id' => $restaurantId,
                'terminal_id'   => $terminalId,
            ],
            'by_restaurant'  => $byRestaurant,
            'sales_7_days'   => $salesByDay,
            'monthly_sales'  => $monthlySales,
            'heatmap'        => $heatmap,
            'heatmap_slots'  => $slots,
            'top_products'   => $topProducts,
            'generated_at'   => now()->toIso8601String(),
        ]);
    }
}
