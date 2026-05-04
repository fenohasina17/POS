<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\CashRegister;
use App\Models\PointOfSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesExportController extends Controller
{
    // Middleware pour restreindre l'accès aux administrateurs uniquement
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            if (!$user || !$user->hasRole('admin')) {
                Log::warning('Tentative d'accès non autorisé à l'export des ventes par un utilisateur non-admin.', ['user_id' => $user->id ?? 'N/A']);
                abort(Response::HTTP_FORBIDDEN, 'Accès non autorisé. Seuls les administrateurs peuvent exporter les ventes.');
            }
            return $next($request);
        });
    }

    /**
     * Exporte les ventes au format CSV selon les filtres spécifiés.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        try {
            $user = Auth::user(); // Middleware a déjà vérifié que c'est un admin
            $filters = $request->validate([
                'pointOfSaleId' => 'nullable|exists:point_of_sales,id',
                'cashRegisterId' => 'nullable|exists:cash_registers,id',
                'startDate' => 'nullable|date_format:Y-m-d',
                'endDate' => 'nullable|date_format:Y-m-d',
            ]);

            $query = Sale::query();

            // Filtrage par Point de Vente
            if (!empty($filters['pointOfSaleId'])) {
                // On pourrait devoir joindre la caisse pour filtrer par POS si ce n'est pas direct sur Sale
                // Pour l'instant, supposons que Sale a une relation ou un champ point_of_sale_id direct ou indirect.
                // Si Sale n'a pas de point_of_sale_id directement, il faut joindre via CashRegister ou PointOfSale.
                // Ici, on suppose que la relation existe ou peut être jointe.
                // Si Sale n'a pas point_of_sale_id, on filtre via cash_register -> point_of_sale_id
                $query->whereHas('cashRegister', function ($q) use ($filters) {
                    $q->where('point_of_sale_id', $filters['pointOfSaleId']);
                });
            }

            // Filtrage par Caisse
            if (!empty($filters['cashRegisterId'])) {
                $query->where('cash_register_id', $filters['cashRegisterId']);
            }

            // Filtrage par période
            if (!empty($filters['startDate'])) {
                $query->whereDate('created_at', '>=', Carbon::parse($filters['startDate'])->startOfDay());
            }
            if (!empty($filters['endDate'])) {
                $query->whereDate('created_at', '<=', Carbon::parse($filters['endDate'])->endOfDay());
            }
            
            // Ajout d'un filtre par défaut si l'admin n'a pas spécifié de POS pour ne pas exporter tout
            // Ou on peut choisir d'exporter tout si aucun filtre n'est appliqué.
            // Pour l'instant, laissons la possibilité de ne pas filtrer si le champ est vide.
            // Si l'admin ne filtre pas, il obtient tout. C'est peut-être le comportement souhaité.

            $sales = $query->with(['orderLines.product', 'pointOfSale', 'cashRegister', 'user'])
                         ->orderBy('created_at', 'desc')
                         ->get();

            if ($sales->isEmpty()) {
                return response()->json(['message' => 'Aucune vente trouvée pour les critères sélectionnés.'], 404);
            }

            // Génération du fichier CSV
            $fileName = 'ventes_export_' . date('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename=' . $fileName,
                'Content-Transfer-Encoding' => 'binary',
                'Expires' => '0',
            ];

            $callback = function() use ($sales) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID Vente', 'Ticket', 'Date Création', 'Statut', 'Montant Total', 'Montant Final', 'Point de Vente', 'Caisse', 'Caissier', 'Produit ID', 'Produit Nom', 'Quantité', 'Prix Unitaire', 'Total Ligne', 'Notes Ligne']); // Header CSV

                foreach ($sales as $sale) {
                    $saleData = [
                        'ID Vente' => $sale->id,
                        'Ticket' => $sale->ticket_number,
                        'Date Création' => $sale->created_at ? $sale->created_at->format('Y-m-d H:i:s') : '',
                        'Statut' => $sale->status,
                        'Montant Total' => $sale->total_amount,
                        'Montant Final' => $sale->final_amount,
                        'Point de Vente' => $sale->point_of_sale->name ?? 'N/A',
                        'Caisse' => $sale->cash_register->name ?? 'N/A',
                        'Caissier' => $sale->user->name ?? 'N/A',
                    ];

                    if (!empty($sale->order_lines)) {
                        foreach ($sale->order_lines as $line) {
                            $row = $saleData; // Start with sale data
                            $row['Produit ID'] = $line->product_id;
                            $row['Produit Nom'] = $line->product->name ?? 'Supprimé';
                            $row['Quantité'] = $line->quantity;
                            $row['Prix Unitaire'] = $line->price;
                            $row['Total Ligne'] = $line->total;
                            $row['Notes Ligne'] = $line->notes ?? '';
                            fputcsv($file, $row);
                        }
                    } else {
                        // If a sale has no order lines (unlikely but possible), write one row for the sale itself
                        // Add empty columns for line item details if needed, or adjust structure
                        $saleData['Produit ID'] = ''; $saleData['Produit Nom'] = ''; $saleData['Quantité'] = ''; $saleData['Prix Unitaire'] = ''; $saleData['Total Ligne'] = ''; $saleData['Notes Ligne'] = '';
                        fputcsv($file, $saleData);
                    }
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l'exportation des ventes : ' . $e->getMessage());
            // Returning a JSON error response as per typical API practices, but the user might expect a download or an alert.
            // For a file download, a direct redirect to an error page or a message might be better UX.
            // For now, returning JSON error.
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de l'export.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
