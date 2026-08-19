<?php

namespace App\Http\Controllers;

use App\Models\RemoteOrderLine;
use App\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ProductReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->date_from
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays(30)->startOfDay();

        $dateTo = $request->date_to
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $region     = $request->region;
        $terminalId = $request->terminal_id;

        // Filtrer par région → liste de terminal_ids
        $terminalIds = null;
        if ($region) {
            $terminalIds = Terminal::where('region', $region)->pluck('terminal_id');
        }
        if ($terminalId) {
            $terminalIds = collect([$terminalId]);
        }

        $base = RemoteOrderLine::query()
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->whereNotNull('product_name')
            ->when($terminalIds, fn($q) => $q->whereIn('terminal_id', $terminalIds));

        // Top produits par CA
        $topByRevenue = (clone $base)
            ->select(
                'product_name',
                'category_name',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('AVG(unit_price) as avg_price'),
                DB::raw('COUNT(DISTINCT terminal_id) as terminal_count')
            )
            ->groupBy('product_name', 'category_name')
            ->orderByDesc('total_revenue')
            ->limit(50)
            ->get();

        // CA par catégorie
        $byCategory = (clone $base)
            ->select(
                DB::raw("COALESCE(category_name, 'Sans catégorie') as category_name"),
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('COUNT(DISTINCT product_name) as product_count')
            )
            ->groupBy(DB::raw("COALESCE(category_name, 'Sans catégorie')"))
            ->orderByDesc('total_revenue')
            ->get();

        // Évolution journalière des ventes (quantité + CA)
        $dailyTrend = (clone $base)
            ->select(
                DB::raw("DATE(remote_created_at) as day"),
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->groupBy(DB::raw('DATE(remote_created_at)'))
            ->orderBy('day')
            ->get();

        // Totaux globaux
        $totals = (clone $base)
            ->selectRaw('SUM(quantity) as total_qty, SUM(total) as total_revenue, COUNT(DISTINCT product_name) as product_count')
            ->first();

        return response()->json([
            'period'       => ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()],
            'filters'      => ['region' => $region, 'terminal_id' => $terminalId],
            'totals'       => $totals,
            'top_products' => $topByRevenue,
            'by_category'  => $byCategory,
            'daily_trend'  => $dailyTrend,
        ]);
    }
}
