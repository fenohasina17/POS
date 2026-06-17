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
        $status = $request->query('status'); // 'open' or 'closed' session status
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

        if ($status) {
            $query->whereHas('cashRegisterSession', function($q) use ($status) {
                $q->where('is_closed', $status === 'closed');
            });
        }

        // 1. Récupération des données selon le filtrage
        if ($posId) {
            $data = $this->aggregateData(clone $query, 'Global pour le site sélectionné', $request);
        } else {
            // Vue globale : grouper par Point de Vente
            $data = \App\Models\PointOfSale::get()->map(function($pos) use ($request, $startDate, $endDate, $status) {
                $posQuery = $pos->sales();
                
                if ($startDate) $posQuery->whereDate('created_at', '>=', $startDate);
                if ($endDate) $posQuery->whereDate('created_at', '<=', $endDate);
                if ($status) {
                    $posQuery->whereHas('cashRegisterSession', function($sq) use ($status) {
                        $sq->where('is_closed', $status === 'closed');
                    });
                }
                
                return [
                    'pos_name' => $pos->name,
                    'data' => $this->aggregateData($posQuery, $pos->name, $request)
                ];
            });
        }

        return response()->json($data);
    }

    private function aggregateData($query, $label, $request)
    {
        $totalSales = (clone $query)->count();
        $totalRevenue = (clone $query)->sum('final_amount');
        $averageTicket = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        $salesIdsQuery = (clone $query)->select('sales.id');

        $paymentSummary = SalePayment::whereIn('sale_id', clone $salesIdsQuery)
            ->join('payments', 'sale_payments.payment_id', '=', 'payments.id')
            ->select('payments.name as method', DB::raw('SUM(sale_payments.amount) as total'))
            ->groupBy('payments.name')
            ->get();

        $productSummary = OrderLine::whereIn('sale_id', clone $salesIdsQuery)
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(order_lines.quantity) as total_qty'), DB::raw('SUM(order_lines.total) as total_revenue'))
            ->groupBy('products.name')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        $flopProducts = OrderLine::whereIn('sale_id', clone $salesIdsQuery)
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(order_lines.quantity) as total_qty'), DB::raw('SUM(order_lines.total) as total_revenue'))
            ->groupBy('products.name')
            ->orderBy('total_qty', 'asc')
            ->limit(5)
            ->get();

        $categorySummary = OrderLine::whereIn('sale_id', clone $salesIdsQuery)
            ->join('products', 'order_lines.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select('categories.name', DB::raw('SUM(order_lines.quantity) as total_qty'), DB::raw('SUM(order_lines.total) as total_revenue'))
            ->groupBy('categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $cashierPerformance = Sale::whereIn('sales.id', clone $salesIdsQuery)
            ->join('users', 'sales.user_id', '=', 'users.id')
            ->select('users.name', DB::raw('COUNT(sales.id) as total_sales'), DB::raw('SUM(sales.final_amount) as total_revenue'))
            ->groupBy('users.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        $totalDiscounts = Sale::whereIn('sales.id', clone $salesIdsQuery)
            ->select(DB::raw('SUM(total_amount - final_amount) as discount_amount'))
            ->value('discount_amount') ?? 0;

        // Évolution des ventes (Heure ou Jour)
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $isSingleDay = ($startDate && $endDate && $startDate === $endDate);
        $timeGranularity = $request->query('time_granularity', 'hour');
        
        $evolutionQuery = clone $query;
        
        if ($isSingleDay) {
            if ($timeGranularity === 'minute') {
                $evolution = $evolutionQuery
                    ->select(DB::raw("TO_CHAR(sales.created_at, 'HH24:MI') as time"), DB::raw('SUM(sales.final_amount) as total'))
                    ->groupBy('time')
                    ->orderBy('time', 'asc')
                    ->get()
                    ->map(fn($item) => ['date' => $item->time, 'total' => $item->total]);
            } else {
                $evolution = $evolutionQuery
                    ->select(DB::raw("TO_CHAR(sales.created_at, 'HH24:00') as time"), DB::raw('SUM(sales.final_amount) as total'))
                    ->groupBy('time')
                    ->orderBy('time', 'asc')
                    ->get()
                    ->map(fn($item) => ['date' => $item->time, 'total' => $item->total]);
            }
        } else {
            $evolution = $evolutionQuery
                ->select(DB::raw("TO_CHAR(sales.created_at, 'YYYY-MM-DD') as date"), DB::raw('SUM(sales.final_amount) as total'))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get()
                ->map(fn($item) => ['date' => $item->date, 'total' => $item->total]);
        }

        return [
            'label' => $label,
            'kpis' => [
                'total_sales' => $totalSales,
                'total_revenue' => $totalRevenue,
                'average_ticket' => $averageTicket,
                'total_discounts' => $totalDiscounts,
            ],
            'payment_summary' => $paymentSummary,
            'top_products' => $productSummary,
            'flop_products' => $flopProducts,
            'category_summary' => $categorySummary,
            'cashier_performance' => $cashierPerformance,
            'sales_evolution' => $evolution
        ];
    }
}
