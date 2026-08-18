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
        $request->validate([
            'format'        => 'required|in:csv,pdf',
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date',
            'restaurant_id' => 'nullable|string',
        ]);

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $sales = RemoteSale::with([])
            ->whereBetween('remote_created_at', [$dateFrom, $dateTo])
            ->when($request->restaurant_id, fn($q) => $q->where('restaurant_id', $request->restaurant_id))
            ->orderBy('remote_created_at')
            ->get();

        $filename = 'ventes_' . $dateFrom->format('Y-m-d') . '_' . $dateTo->format('Y-m-d');

        if ($request->format === 'csv') {
            return $this->exportCsv($sales, $filename);
        }

        return $this->exportPdf($sales, $filename, $dateFrom, $dateTo, $request->restaurant_id);
    }

    private function exportCsv($sales, string $filename): Response
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ];

        $lines = [];
        $lines[] = implode(';', [
            'Date', 'Terminal', 'Restaurant', 'N° Ticket',
            'Montant HT', 'Remise', 'Montant TTC', 'Statut',
        ]);

        foreach ($sales as $sale) {
            $lines[] = implode(';', [
                $sale->remote_created_at?->format('d/m/Y H:i'),
                $sale->terminal_id,
                $sale->restaurant_id,
                $sale->ticket_number ?? '',
                number_format($sale->total_amount ?? 0, 2, ',', ''),
                number_format($sale->discount_amount ?? 0, 2, ',', ''),
                number_format($sale->final_amount ?? 0, 2, ',', ''),
                $sale->status ?? '',
            ]);
        }

        // BOM UTF-8 pour compatibilité Excel
        $content = "\xEF\xBB\xBF" . implode("\n", $lines);

        return response($content, 200, $headers);
    }

    private function exportPdf($sales, string $filename, Carbon $from, Carbon $to, ?string $restaurantId): Response
    {
        $totalRevenue = $sales->where('status', 'completed')->sum('final_amount');
        $totalSales   = $sales->count();
        $byRestaurant = $sales->where('status', 'completed')
            ->groupBy('restaurant_id')
            ->map(fn($g) => ['count' => $g->count(), 'revenue' => $g->sum('final_amount')]);

        $periodLabel = $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y');

        $html = $this->buildPdfHtml($sales, $totalRevenue, $totalSales, $byRestaurant, $periodLabel, $restaurantId);

        $headers = [
            'Content-Type'        => 'text/html; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.html\"",
        ];

        return response($html, 200, $headers);
    }

    private function buildPdfHtml($sales, float $total, int $count, $byRestaurant, string $period, ?string $restaurantId): string
    {
        $formatCurrency = fn($v) => number_format($v, 2, ',', ' ') . ' €';

        $rows = '';
        foreach ($sales as $sale) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td class="right">%s</td><td class="right">%s</td><td class="center status-%s">%s</td></tr>',
                htmlspecialchars($sale->remote_created_at?->format('d/m/Y H:i') ?? ''),
                htmlspecialchars($sale->terminal_id),
                htmlspecialchars($sale->restaurant_id),
                htmlspecialchars($sale->ticket_number ?? '—'),
                $formatCurrency($sale->total_amount ?? 0),
                $formatCurrency($sale->final_amount ?? 0),
                $sale->status ?? 'unknown',
                ucfirst($sale->status ?? '—'),
            );
        }

        $summaryRows = '';
        foreach ($byRestaurant as $restId => $data) {
            $summaryRows .= sprintf(
                '<tr><td>%s</td><td class="right">%d</td><td class="right">%s</td></tr>',
                htmlspecialchars($restId),
                $data['count'],
                $formatCurrency($data['revenue']),
            );
        }

        $filterLabel = $restaurantId ? " — Restaurant : {$restaurantId}" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Export ventes {$period}</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 12px; color: #1a1a1a; margin: 30px; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  .meta { color: #666; font-size: 11px; margin-bottom: 20px; }
  .summary { display: flex; gap: 30px; margin-bottom: 24px; }
  .kpi { background: #f5f5f5; border-radius: 6px; padding: 12px 20px; }
  .kpi-label { font-size: 10px; color: #888; text-transform: uppercase; }
  .kpi-value { font-size: 20px; font-weight: bold; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
  th { background: #1a1a1a; color: #fff; padding: 8px 10px; text-align: left; font-size: 11px; }
  td { padding: 6px 10px; border-bottom: 1px solid #e5e5e5; }
  tr:nth-child(even) td { background: #fafafa; }
  .right { text-align: right; }
  .center { text-align: center; }
  .status-completed { color: #16a34a; font-weight: bold; }
  .status-cancelled { color: #dc2626; }
  h2 { font-size: 13px; margin: 24px 0 8px; border-bottom: 2px solid #1a1a1a; padding-bottom: 4px; }
  @media print { body { margin: 15px; } }
</style>
</head>
<body>
  <h1>Rapport des ventes</h1>
  <p class="meta">Période : {$period}{$filterLabel} · Généré le {$this->now()}</p>

  <div class="summary">
    <div class="kpi"><div class="kpi-label">Total ventes</div><div class="kpi-value">{$count}</div></div>
    <div class="kpi"><div class="kpi-label">Chiffre d'affaires</div><div class="kpi-value">{$formatCurrency($total)}</div></div>
  </div>

  <h2>Récapitulatif par restaurant</h2>
  <table>
    <thead><tr><th>Restaurant</th><th>Ventes</th><th>CA</th></tr></thead>
    <tbody>{$summaryRows}</tbody>
  </table>

  <h2>Détail des ventes</h2>
  <table>
    <thead>
      <tr>
        <th>Date</th><th>Terminal</th><th>Restaurant</th>
        <th>Ticket</th><th>Montant HT</th><th>Montant TTC</th><th>Statut</th>
      </tr>
    </thead>
    <tbody>{$rows}</tbody>
  </table>
</body>
</html>
HTML;
    }

    private function now(): string
    {
        return now()->format('d/m/Y à H:i');
    }
}
