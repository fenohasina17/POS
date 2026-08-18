<?php

namespace App\Http\Controllers;

use App\Models\Terminal;
use App\Models\RemoteSale;
use App\Models\RemoteCashRegisterSession;
use App\Models\RemoteCashTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Vue d'ensemble pour le dashboard siège.
     * GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $restaurantId = $request->restaurant_id;
        $dateFrom     = $request->filled('date_from') ? \Carbon\Carbon::parse($request->date_from)->startOfDay() : now()->startOfDay();
        $dateTo       = $request->filled('date_to')   ? \Carbon\Carbon::parse($request->date_to)->endOfDay()     : now()->endOfDay();
        $prevFrom     = $dateFrom->copy()->subDay();
        $prevTo       = $dateTo->copy()->subDay();

        // Statut des terminaux
        $terminals     = Terminal::when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))->get();
        $onlineCount   = $terminals->filter->is_online->count();
        $offlineCount  = $terminals->count() - $onlineCount;
        $pendingTotal  = $terminals->sum('pending_sync_count');

        // Ventes sur la période sélectionnée
        $salesQuery = RemoteSale::where('status', 'completed')
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId));

        $revenueToday     = (clone $salesQuery)->whereBetween('remote_created_at', [$dateFrom, $dateTo])->sum('final_amount');
        $salesCountToday  = (clone $salesQuery)->whereBetween('remote_created_at', [$dateFrom, $dateTo])->count();
        $revenuePrev      = (clone $salesQuery)->whereBetween('remote_created_at', [$prevFrom, $prevTo])->sum('final_amount');

        // Ventes par restaurant sur la période
        $salesByRestaurant = RemoteSale::select('restaurant_id',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->groupBy('restaurant_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Courbe : nb de jours entre dateFrom et dateTo (max 90)
        $chartDays = min(90, $dateFrom->diffInDays($dateTo) + 1);
        $salesByDay = RemoteSale::select(
                DB::raw("DATE(remote_created_at) as date"),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', now()->subDays($chartDays))
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'terminals' => [
                'total'         => $terminals->count(),
                'online'        => $onlineCount,
                'offline'       => $offlineCount,
                'pending_sync'  => $pendingTotal,
            ],
            'sales_today' => [
                'count'             => $salesCountToday,
                'revenue'           => round($revenueToday, 2),
                'revenue_yesterday' => round($revenuePrev, 2),
                'evolution_pct'     => $revenuePrev > 0
                    ? round((($revenueToday - $revenuePrev) / $revenuePrev) * 100, 1)
                    : null,
            ],
            'filters' => [
                'date_from'     => $dateFrom->toDateString(),
                'date_to'       => $dateTo->toDateString(),
                'restaurant_id' => $restaurantId,
            ],
            'by_restaurant' => $salesByRestaurant,
            'sales_7_days'  => $salesByDay,
            'generated_at'  => now()->toIso8601String(),
        ]);
    }
}
