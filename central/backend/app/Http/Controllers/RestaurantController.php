<?php

namespace App\Http\Controllers;

use App\Models\RemoteSale;
use App\Models\RemoteOrderLine;
use App\Models\Terminal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo   = $request->query('date_to');

        // Liste des restaurants connus depuis les terminaux et les ventes
        $restaurantIds = Terminal::distinct()->pluck('restaurant_id')
            ->merge(RemoteSale::distinct()->pluck('restaurant_id'))
            ->filter()
            ->unique()
            ->values();

        $restaurants = $restaurantIds->map(function (string $restaurantId) use ($dateFrom, $dateTo) {
            $salesBase = RemoteSale::where('restaurant_id', $restaurantId)
                ->where('status', 'completed');

            if ($dateFrom) $salesBase->whereDate('remote_created_at', '>=', $dateFrom);
            if ($dateTo)   $salesBase->whereDate('remote_created_at', '<=', $dateTo);

            $kpis = (clone $salesBase)->selectRaw('
                COUNT(*) as sales_count,
                COALESCE(SUM(final_amount), 0) as ca,
                COALESCE(AVG(final_amount), 0) as avg_basket
            ')->first();

            $terminals = Terminal::where('restaurant_id', $restaurantId)->get([
                'terminal_id', 'status', 'last_heartbeat_at', 'last_sync_at',
            ]);

            $topSellers = (clone $salesBase)
                ->whereNotNull('seller_name')
                ->selectRaw('seller_name, COUNT(*) as nb, SUM(final_amount) as ca')
                ->groupBy('seller_name')
                ->orderByDesc('ca')
                ->limit(3)
                ->get();

            $topProducts = RemoteOrderLine::where('restaurant_id', $restaurantId)
                ->whereNotNull('product_name')
                ->when($dateFrom, fn($q) => $q->whereDate('remote_created_at', '>=', $dateFrom))
                ->when($dateTo,   fn($q) => $q->whereDate('remote_created_at', '<=', $dateTo))
                ->selectRaw('product_name, SUM(quantity) as qty, SUM(total) as ca')
                ->groupBy('product_name')
                ->orderByDesc('ca')
                ->limit(3)
                ->get();

            // Tendance 7 derniers jours
            $trend = (clone $salesBase)
                ->where('remote_created_at', '>=', now()->subDays(7))
                ->selectRaw("DATE(remote_created_at) as day, SUM(final_amount) as ca")
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            $onlineCount  = $terminals->where('status', 'online')->count();
            $offlineCount = $terminals->where('status', '!=', 'online')->count();

            return [
                'restaurant_id' => $restaurantId,
                'ca'            => floatval($kpis->ca),
                'sales_count'   => intval($kpis->sales_count),
                'avg_basket'    => floatval($kpis->avg_basket),
                'terminals'     => [
                    'total'   => $terminals->count(),
                    'online'  => $onlineCount,
                    'offline' => $offlineCount,
                    'list'    => $terminals,
                ],
                'top_sellers'  => $topSellers,
                'top_products' => $topProducts,
                'trend'        => $trend,
            ];
        })->sortByDesc('ca')->values();

        return response()->json([
            'restaurants' => $restaurants,
            'totals' => [
                'ca'          => $restaurants->sum('ca'),
                'sales_count' => $restaurants->sum('sales_count'),
                'restaurants' => $restaurants->count(),
                'terminals'   => $restaurants->sum(fn($r) => $r['terminals']['total']),
            ],
        ]);
    }
}
