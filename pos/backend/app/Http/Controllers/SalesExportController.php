<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SalesExportController extends Controller
{
    /**
     * Exporte les ventes au format CSV selon les filtres (POS, Période, Produit).
     */
    public function export(Request $request)
    {
        try {
            $validated = $request->validate([
                'pointOfSaleId' => 'nullable|exists:point_of_sales,id',
                'productId'     => 'nullable|exists:products,id',
                'day'           => 'nullable|date_format:Y-m-d',
                'week'          => 'nullable|string|regex:/^\d{4}-W\d{1,2}$/',
                'month'         => 'nullable|date_format:Y-m',
                'year'          => 'nullable|digits:4',
                'startDate'     => 'nullable|date_format:Y-m-d',
                'endDate'       => 'nullable|date_format:Y-m-d|after_or_equal:startDate',
            ]);

            $query = Sale::query();

            // Filtrage Point de Vente
            if (!empty($validated['pointOfSaleId'])) {
                $query->where('point_of_sale_id', $validated['pointOfSaleId']);
            }

            // Filtrage par Produit (via order_lines)
            if (!empty($validated['productId'])) {
                $query->whereHas('orderLines', function ($q) use ($validated) {
                    $q->where('product_id', $validated['productId']);
                });
            }

            // Filtrage Temporel
            if (!empty($validated['day'])) {
                $query->whereDate('created_at', $validated['day']);
            } elseif (!empty($validated['week'])) {
                [$year, $week] = explode('-W', $validated['week']);
                $query->whereBetween('created_at', [
                    Carbon::now()->setISODate($year, $week)->startOfWeek(),
                    Carbon::now()->setISODate($year, $week)->endOfWeek()
                ]);
            } elseif (!empty($validated['month'])) {
                $query->whereMonth('created_at', Carbon::parse($validated['month'])->month)
                      ->whereYear('created_at', Carbon::parse($validated['month'])->year);
            } elseif (!empty($validated['year'])) {
                $query->whereYear('created_at', $validated['year']);
            } elseif (!empty($validated['startDate'])) {
                $query->whereDate('created_at', '>=', Carbon::parse($validated['startDate'])->startOfDay());
                if (!empty($validated['endDate'])) {
                    $query->whereDate('created_at', '<=', Carbon::parse($validated['endDate'])->endOfDay());
                }
            }

            $sales = $query->with(['orderLines.product', 'pointOfSale'])->get();

            if ($sales->isEmpty()) {
                return response()->json(['message' => 'Aucune vente trouvée.'], 404);
            }

            $fileName = 'export_ventes_' . Carbon::now()->format('Ymd_His') . '.csv';

            $callback = function () use ($sales, $validated) {
                $handle = fopen('php://output', 'w');
                fputs($handle, "\xEF\xBB\xBF"); // BOM UTF-8

                fputcsv($handle, [
                    'ID Vente', 'Ticket', 'Date Vente', 'Point de vente',
                    'Produit', 'Quantité', 'Prix Unitaire', 'Total Ligne'
                ], ';');

                foreach ($sales as $sale) {
                    // Filtrage des lignes au niveau de la collection pour n'avoir QUE le produit demandé
                    $lines = !empty($validated['productId']) 
                        ? $sale->orderLines->where('product_id', $validated['productId'])
                        : $sale->orderLines;

                    foreach ($lines as $line) {
                        fputcsv($handle, [
                            $sale->id,
                            $sale->ticket_number,
                            $sale->created_at?->format('Y-m-d H:i:s'),
                            $sale->pointOfSale?->name ?? 'N/A',
                            $line->product?->name ?? 'Supprimé',
                            $line->quantity,
                            number_format($line->price, 2, ',', ' '),
                            number_format($line->total, 2, ',', ' ')
                        ], ';');
                    }
                }
                fclose($handle);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
            ]);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Export CSV Error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur serveur lors de l\'export.', 'details' => $e->getMessage()], 500);
        }
    }
}
