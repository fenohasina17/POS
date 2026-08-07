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
        $today        = now()->startOfDay();
        $yesterday    = now()->subDay()->startOfDay();

        // Statut des terminaux
        $terminals     = Terminal::when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))->get();
        $onlineCount   = $terminals->filter->is_online->count();
        $offlineCount  = $terminals->count() - $onlineCount;
        $pendingTotal  = $terminals->sum('pending_sync_count');

        // Ventes du jour
        $salesQuery = RemoteSale::where('status', 'completed')
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId));

        $revenueToday     = (clone $salesQuery)->where('remote_created_at', '>=', $today)->sum('final_amount');
        $salesCountToday  = (clone $salesQuery)->where('remote_created_at', '>=', $today)->count();
        $revenueYesterday = (clone $salesQuery)
            ->whereBetween('remote_created_at', [$yesterday, $today])
            ->sum('final_amount');

        // Ventes par restaurant (top 10)
        $salesByRestaurant = RemoteSale::select('restaurant_id',
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', $today)
            ->when($restaurantId, fn($q) => $q->where('restaurant_id', $restaurantId))
            ->groupBy('restaurant_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Ventes des 7 derniers jours (courbe)
        $salesByDay = RemoteSale::select(
                DB::raw("DATE(remote_created_at) as date"),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(final_amount) as revenue')
            )
            ->where('status', 'completed')
            ->where('remote_created_at', '>=', now()->subDays(7))
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
                'revenue_yesterday' => round($revenueYesterday, 2),
                'evolution_pct'     => $revenueYesterday > 0
                    ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1)
                    : null,
            ],
            'by_restaurant' => $salesByRestaurant,
            'sales_7_days'  => $salesByDay,
            'generated_at'  => now()->toIso8601String(),
        ]);
    }
}
