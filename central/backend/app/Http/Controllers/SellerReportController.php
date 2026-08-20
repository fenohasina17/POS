<?php

namespace App\Http\Controllers;

use App\Models\RemoteSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SellerReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $dateFrom   = $request->query('date_from');
        $dateTo     = $request->query('date_to');
        $terminalId = $request->query('terminal_id');
        $posName    = $request->query('point_of_sale_name');

        $base = RemoteSale::query()
            ->where('status', 'completed')
            ->whereNotNull('seller_name');

        if ($dateFrom) $base->whereDate('remote_created_at', '>=', $dateFrom);
        if ($dateTo)   $base->whereDate('remote_created_at', '<=', $dateTo);
        if ($terminalId) $base->where('terminal_id', $terminalId);
        if ($posName)    $base->where('point_of_sale_name', $posName);

        // KPIs globaux
        $totals = (clone $base)->selectRaw('
            COUNT(*) as total_sales,
            COALESCE(SUM(final_amount), 0) as total_ca,
            COUNT(DISTINCT seller_name) as total_sellers
        ')->first();

        // Top vendeurs
        $sellers = (clone $base)->selectRaw('
            seller_name,
            point_of_sale_name,
            COUNT(*) as sales_count,
            COALESCE(SUM(final_amount), 0) as ca,
            COALESCE(AVG(final_amount), 0) as avg_basket,
            COALESCE(MAX(final_amount), 0) as max_sale
        ')
        ->groupBy('seller_name', 'point_of_sale_name')
        ->orderByDesc('ca')
        ->limit(50)
        ->get();

        // Tendance journalière par vendeur (top 5)
        $top5 = $sellers->take(5)->pluck('seller_name');
        $daily = (clone $base)
            ->whereIn('seller_name', $top5)
            ->selectRaw("DATE(remote_created_at) as day, seller_name, COALESCE(SUM(final_amount), 0) as ca")
            ->groupBy('day', 'seller_name')
            ->orderBy('day')
            ->get()
            ->groupBy('seller_name');

        // Répartition par point de vente
        $byPos = (clone $base)->selectRaw('
            point_of_sale_name,
            COUNT(DISTINCT seller_name) as sellers_count,
            COUNT(*) as sales_count,
            COALESCE(SUM(final_amount), 0) as ca
        ')
        ->groupBy('point_of_sale_name')
        ->orderByDesc('ca')
        ->get();

        return response()->json([
            'totals'  => $totals,
            'sellers' => $sellers,
            'daily'   => $daily,
            'by_pos'  => $byPos,
        ]);
    }
}
