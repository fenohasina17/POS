<?php

namespace App\Services;

use App\Models\CashRegisterSession;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CashRegisterSessionSummaryService
{
    /**
     * Génère un résumé complet d'une session de caisse.
     * Accessible par les Gérants (Ventes) et les Admins (Ventes + Finance).
     */
    public function build(CashRegisterSession $session): array
    {
        // Récupération de l'utilisateur connecté via Sanctum
        $user = Auth::user();

        // Récupération de toutes les ventes liées à cette session avec les relations nécessaires
        // orderLines.product.category : pour le détail par produits et catégories
        // payments.payment : pour le détail par modes de paiement (Espèces, CB, etc.)
        $sales = Sale::with(['orderlines.product.category', 'payments.payment'])
            ->where('cash_register_session_id', $session->id)
            ->get();

        // --- 1. GÉNÉRATION DU RÉSUMÉ PAR CATÉGORIE ---
        // On aplatit toutes les lignes de commande de toutes les ventes de la session
        $categorySummary = $sales->flatMap(fn($sale) => $sale->orderlines)
            ->groupBy(fn($line) => optional($line->product)->category_id ?? 'uncategorized')
            ->map(function ($lines) {
                $category = optional($lines->first()->product)->category;
                
                // Sous-groupement par produit pour avoir le détail des quantités vendues
                $products = $lines->groupBy('product_id')->map(fn($pLines) => [
                    'product_id'   => $pLines->first()->product_id,
                    'product_name' => optional($pLines->first()->product)->name ?? 'Produit supprimé',
                    'quantity'     => (float) $pLines->sum('quantity'),
                    'amount'       => (float) $pLines->sum('total'),
                ])->values();

                return [
                    'category_id'   => $category?->id,
                    'category_name' => $category?->name ?? 'Non catégorisé',
                    'amount'        => (float) $products->sum('amount'),
                    'products'      => $products,
                ];
            })->values();

        // --- 2. GÉNÉRATION DU RÉSUMÉ PAR MODE DE PAIEMENT ---
        // On récupère tous les paiements effectués durant la session
        $allPayments = $sales->flatMap(fn($sale) => $sale->payments);
        $paymentTotals = $allPayments->groupBy('payment_id')->map(fn($group) => [
            'total'        => (float) $group->sum('amount'),
            'transactions' => $group->count(),
        ]);

        // On boucle sur TOUS les modes de paiement existants pour inclure ceux à 0€
        $paymentSummary = Payment::all()->map(function ($p) use ($paymentTotals) {
            $data = $paymentTotals->get($p->id) ?? ['total' => 0, 'transactions' => 0];
            return [
                'payment_id'   => $p->id,
                'payment_name' => $p->name,
                'total'        => (float) $data['total'],
                'transactions' => (int) $data['transactions'],
            ];
        });

        // --- 3. PRÉPARATION DE LA RÉPONSE COMMUNE (Visible par Gérant & Admin) ---
        $response = [
            'session'     => $session->load('user', 'cashRegister'), // Détails de la session (caissier, caisse)
            'categories'  => $categorySummary,                     // Détails des ventes par rayon
            'payments'    => $paymentSummary->values(),           // Détails par type d'encaissement
            'total_sales' => (float) $sales->sum('final_amount'), // Chiffre d'affaires total brut
        ];

        // --- 4. SECTION SÉCURISÉE (Réservée uniquement à l'ADMIN) ---
        // Seul l'administrateur peut voir si l'argent en caisse correspond aux ventes
        if ($user && $user->hasRole('admin')) {
            $startingAmount = (float) ($session->starting_amount ?? 0); // Fond de caisse initial
            
            // Calcul spécifique des ventes en "Cash" (Espèces) via le helper privé
            $cashSalesTotal = $this->computeCashSalesTotal($paymentSummary);
            
            // Théorique : Ce qu'il devrait y avoir dans le tiroir (Ventes Espèces + Fond initial)
            $expectedCashInDrawer = $cashSalesTotal + $startingAmount;
            
            // Réel : Ce que le caissier a déclaré avoir compté à la fermeture
            $actualCashCounted = (float) ($session->actual_cash_amount ?? 0);
            
            // Écart de caisse (Positif = surplus, Négatif = manque d'argent)
            $cashDifference = $actualCashCounted - $expectedCashInDrawer;

            $response['admin_finance'] = [
                'starting_amount'         => $startingAmount,
                'cash_sales_total'        => $cashSalesTotal,
                'expected_cash_in_drawer' => $expectedCashInDrawer,
                'actual_cash_counted'     => $actualCashCounted,
                'cash_difference'         => $cashDifference,
                'is_balanced'             => $cashDifference === 0.0, // Indique si la caisse est juste
            ];
        }

        return $response;
    }

    /**
     * Helper pour identifier les paiements en espèces par mots-clés
     * Gère les accents et la casse (ex: Espèces, Cash, Liquide)
     */
    private function computeCashSalesTotal(Collection $paymentSummary): float
    {
        $keywords = ['esp', 'cash', 'liq']; 
        
        return $paymentSummary->reduce(function ($carry, $payment) use ($keywords) {
            $name = strtolower($payment['payment_name'] ?? '');
            
            // Normalisation : remplace les caractères accentués par leur équivalent simple
            $normalized = str_replace(['è', 'é', 'ê', 'ë'], 'e', $name);
            
            // Si le nom du mode de paiement contient un des mots-clés, on l'ajoute au total Cash
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    return $carry + (float)$payment['total'];
                }
            }
            return $carry;
        }, 0.0);
    }
}