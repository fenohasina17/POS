<?php

namespace App\Http\Controllers;

use App\Models\RemoteSale;
use App\Models\RemoteOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Carbon\Carbon;

class ExportController extends Controller
{
    public function sales(Request $request): Response
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $sales = RemoteSale::query()
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($request->terminal_id,  fn($q) => $q->where('terminal_id',  $request->terminal_id))
            ->when($request->seller_name,  fn($q) => $q->where('seller_name',  $request->seller_name))
            ->orderBy('remote_created_at')
            ->get();

        $filename = 'ventes_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d');

        return $this->toCsv($sales, $filename, [
            'Date'          => fn($s) => $s->remote_created_at?->format('d/m/Y H:i'),
            'Terminal'      => fn($s) => $s->terminal_id,
            'Point de vente'=> fn($s) => $s->point_of_sale_name ?? $s->restaurant_id,
            'Vendeur'       => fn($s) => $s->seller_name ?? '',
            'N° Ticket'     => fn($s) => $s->ticket_number ?? $s->sale_number ?? '',
            'Montant brut'  => fn($s) => number_format($s->total_amount ?? 0, 0, ',', ''),
            'Remise %'      => fn($s) => $s->discount_percentage ?? 0,
            'Montant net'   => fn($s) => number_format($s->final_amount ?? 0, 0, ',', ''),
            'Statut'        => fn($s) => $s->status ?? '',
        ]);
    }

    public function sellers(Request $request): Response
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $rows = RemoteSale::query()
            ->where('status', 'completed')
            ->whereNotNull('seller_name')
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($request->terminal_id, fn($q) => $q->where('terminal_id', $request->terminal_id))
            ->selectRaw('
                seller_name,
                point_of_sale_name,
                COUNT(*) as sales_count,
                COALESCE(SUM(final_amount), 0) as ca,
                COALESCE(AVG(final_amount), 0) as avg_basket,
                COALESCE(MAX(final_amount), 0) as max_sale
            ')
            ->groupBy('seller_name', 'point_of_sale_name')
            ->orderByDesc('ca')
            ->get();

        $filename = 'vendeurs_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d');

        return $this->toCsv($rows, $filename, [
            'Vendeur'        => fn($r) => $r->seller_name,
            'Point de vente' => fn($r) => $r->point_of_sale_name ?? '',
            'Nb ventes'      => fn($r) => $r->sales_count,
            'CA (Ar)'        => fn($r) => number_format($r->ca, 0, ',', ''),
            'Panier moyen'   => fn($r) => number_format($r->avg_basket, 0, ',', ''),
            'Meilleure vente'=> fn($r) => number_format($r->max_sale, 0, ',', ''),
        ]);
    }

    public function products(Request $request): Response
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfMonth()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $rows = RemoteOrderLine::query()
            ->whereNotNull('product_name')
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($request->terminal_id,  fn($q) => $q->where('terminal_id',  $request->terminal_id))
            ->when($request->category_name,fn($q) => $q->where('category_name',$request->category_name))
            ->selectRaw('
                product_name,
                category_name,
                COUNT(*) as orders,
                COALESCE(SUM(quantity), 0) as total_qty,
                COALESCE(SUM(total), 0) as ca,
                COALESCE(AVG(unit_price), 0) as avg_price
            ')
            ->groupBy('product_name', 'category_name')
            ->orderByDesc('ca')
            ->get();

        $filename = 'produits_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d');

        return $this->toCsv($rows, $filename, [
            'Produit'        => fn($r) => $r->product_name,
            'Catégorie'      => fn($r) => $r->category_name ?? '',
            'Nb commandes'   => fn($r) => $r->orders,
            'Quantité totale'=> fn($r) => $r->total_qty,
            'CA (Ar)'        => fn($r) => number_format($r->ca, 0, ',', ''),
            'Prix moyen'     => fn($r) => number_format($r->avg_price, 0, ',', ''),
        ]);
    }

    private function toCsv($rows, string $filename, array $columns): Response
    {
        $lines   = [];
        $lines[] = implode(';', array_keys($columns));

        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(fn($fn) => $fn($row), $columns));
        }

        // BOM UTF-8 pour compatibilité Excel
        $content = "\xEF\xBB\xBF" . implode("\n", $lines);

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }
}
