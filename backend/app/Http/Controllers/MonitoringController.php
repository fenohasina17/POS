<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\OrderLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MonitoringController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized.');
        }

        $posId = $request->query('pos_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Sale::query();

        if ($posId) {
            $query->where('point_of_sale_id', $posId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        // 1. Récupération des données selon le filtrage
        if ($posId) {
            $sales = Sale::where('point_of_sale_id', $posId)->get();
            $data = $this->aggregateData($sales, 'Global pour le site sélectionné');
        } else {
            // Vue globale : grouper par Point de Vente
            $data = \App\Models\PointOfSale::with(['sales' => function($q) use ($startDate, $endDate) {
                if ($startDate) $q->whereDate('created_at', '>=', $startDate);
                if ($endDate) $q->whereDate('created_at', '<=', $endDate);
            }])->get()->map(function($pos) {
                return [
                    'pos_name' => $pos->name,
                    'data' => $this->aggregateData($pos->sales, $pos->name)
                ];
            });
        }

        return response()->json($data);
    }

    private function aggregateData($sales, $label)
    {
        $totalSales = $sales->count();
        $totalRevenue = $sales->sum('final_amount');
        $averageTicket = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        $paymentSummary = SalePayment::whereIn('sale_id', $sales->pluck('id'))
            ->join('payments', 'sale_payments.payment_id', '=', 'payments.id')
            ->select('payments.name as method', DB::raw('SUM(sale_payments.amount) as total'))
            ->groupBy('payments.name')
            ->get();

        $productSummary = OrderLine::whereIn('sale_id', $sales->pluck('id'))
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(order_lines.quantity) as total_qty'), DB::raw('SUM(order_lines.total) as total_revenue'))
            ->groupBy('products.name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        return [
            'label' => $label,
            'kpis' => [
                'total_sales' => $totalSales,
                'total_revenue' => $totalRevenue,
                'average_ticket' => $averageTicket,
            ],
            'payment_summary' => $paymentSummary,
            'top_products' => $productSummary,
        ];
    }
}
