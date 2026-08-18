<?php
// app/Services/SalePaymentService.php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalePaymentService
{
    /**
     * Ajoute plusieurs paiements à une vente et met à jour son statut si nécessaire
     * 
     * Permet d'enregistrer un ou plusieurs paiements pour une commande en attente.
     * Si le total payé atteint ou dépasse le montant final, la vente passe en statut 'completed'.
     * Une remise peut être appliquée via n'importe quel paiement du tableau.
     *
     * @param Sale $sale Vente concernée (doit avoir le statut 'pending')
     * @param array $paymentsData Tableau des paiements à ajouter
     *                            FORMAT :
     *                            [
     *                                [
     *                                    'payment_id' => int,           // REQUIS - ID du mode de paiement (existe dans payments)
     *                                    'amount' => float,              // REQUIS - Montant payé (>0)
     *                                    'reference' => string|null,     // OPTIONNEL - Référence du paiement (ex: numéro de chèque)
     *                                    'notes' => string|null,         // OPTIONNEL - Notes supplémentaires
     *                                    'discount_percentage' => float  // OPTIONNEL - Remise en % (0-100) appliquée à la vente
     *                                ],
     *                                ...
     *                            ]
     * @param float|null $forcedChangeAmount Montant de monnaie rendue forcé (optionnel)
     *                                       Si non fourni, calculé automatiquement : max(0, total_payé - montant_final)
     * @return array Retourne un tableau avec les informations suivantes :
     *               [
     *                   'total_paid_now' => float,      // Montant payé lors de cet appel
     *                   'new_total_paid' => float,      // Montant total payé après cet appel
     *                   'change_amount' => float,       // Monnaie rendue
     *                   'is_completed' => bool,         // True si la vente est maintenant complètement payée
     *                   'remaining' => float,           // Montant restant à payer (0 si complété)
     *                   'new_final_amount' => float,    // Montant final après remise (si modifiée)
     *                   'new_discount' => float         // Pourcentage de remise appliqué
     *               ]
     * 
     * @throws InvalidArgumentException Dans les cas suivants :
     *                                  - La vente n'est pas en statut 'pending'
     *                                  - Le tableau des paiements est vide
     *                                  - Un paiement est mal formaté (payment_id manquant ou amount <= 0)
     */
    public function addPayments(Sale $sale, array $paymentsData, ?float $forcedChangeAmount = null): array
    {
        if ($sale->status !== 'pending') {
            throw new InvalidArgumentException("Seule une vente en attente peut recevoir des paiements.");
        }

        if (empty($paymentsData)) {
            throw new InvalidArgumentException("Au moins un paiement est requis.");
        }

        $totalPaidNow = 0;
        $newDiscount = $sale->discount_percentage;

        DB::transaction(function () use ($sale, $paymentsData, &$totalPaidNow, &$newDiscount) {
            foreach ($paymentsData as $data) {
                if (!isset($data['payment_id']) || !isset($data['amount']) || $data['amount'] <= 0) {
                    throw new InvalidArgumentException("Chaque paiement doit avoir un payment_id et un amount > 0.");
                }

                // ✅ Vérifier si une remise est fournie dans ce paiement
                if (isset($data['discount_percentage']) && $data['discount_percentage'] > 0) {
                    $newDiscount = $data['discount_percentage'];
                    
                    $newFinalAmount = round($sale->total_amount * (1 - ($newDiscount / 100)), 2);
                    
                    $sale->update([
                        'discount_percentage' => $newDiscount,
                        'final_amount' => $newFinalAmount,
                    ]);
                    
                    $sale->refresh();
                }

                SalePayment::create([
                    'sale_id'     => $sale->id,
                    'payment_id'  => $data['payment_id'],
                    'amount'      => $data['amount'],
                    'reference'   => $data['reference'] ?? null,
                    'notes'       => $data['notes'] ?? null,
                ]);

                $totalPaidNow += $data['amount'];
            }

            $alreadyPaid   = $sale->payments()->sum('amount');
            $newTotalPaid  = $alreadyPaid + $totalPaidNow;
            $finalAmount   = $sale->final_amount;

            $changeAmount = $forcedChangeAmount ?? max(0, $newTotalPaid - $finalAmount);

            $sale->update([
                'paid_amount'   => $newTotalPaid,
                'change_amount' => $changeAmount,
                'status'        => $newTotalPaid >= $finalAmount ? 'completed' : 'pending',
            ]);
        });

        $sale->refresh();

        return [
            'total_paid_now'   => $totalPaidNow,
            'new_total_paid'   => $sale->paid_amount,
            'change_amount'    => $sale->change_amount,
            'is_completed'     => $sale->status === 'completed',
            'remaining'        => max(0, $sale->final_amount - $sale->paid_amount),
            'new_final_amount' => $sale->final_amount,
            'new_discount'     => $sale->discount_percentage,
        ];
    }

    /**
     * Version simplifiée qui retourne directement le résultat formaté pour l'API
     * 
     * Cette méthode est un wrapper de addPayments() qui formate la réponse
     * spécifiquement pour une API REST, incluant les relations chargées.
     *
     * @param Sale $sale Vente concernée (doit avoir le statut 'pending')
     * @param array $paymentsData Tableau des paiements (même format que addPayments)
     * @param float|null $forcedChangeAmount Montant de monnaie rendue forcé (optionnel)
     * @return array Retourne un tableau formaté pour l'API :
     *               [
     *                   'message' => string,           // Message de confirmation
     *                   'sale' => Sale,                // Instance de Sale avec relations 'payments.payment' chargées
     *                   'total_paid_now' => float,     // Montant payé lors de cet appel
     *                   'remaining' => float,          // Montant restant à payer
     *                   'change' => float,             // Monnaie rendue
     *                   'is_completed' => bool,        // True si la vente est complètement payée
     *                   'discount_applied' => float    // Pourcentage de remise appliqué
     *               ]
     * 
     * @throws InvalidArgumentException Les mêmes exceptions que addPayments()
     */
    public function addPaymentsAndFormatResponse(Sale $sale, array $paymentsData, ?float $forcedChangeAmount = null): array
    {
        $result = $this->addPayments($sale, $paymentsData, $forcedChangeAmount);
        
        $sale->load('payments.payment');
        
        return [
            'message' => 'Paiement(s) enregistré(s)',
            'sale' => $sale,
            'total_paid_now' => $result['total_paid_now'],
            'remaining' => $result['remaining'],
            'change' => $result['change_amount'],
            'is_completed' => $result['is_completed'],
            'discount_applied' => $result['new_discount'],
        ];
    }

    /**
     * Calcule si une vente est complètement payée
     * 
     * Vérifie si le montant total payé est supérieur ou égal au montant final
     *
     * @param Sale $sale Vente à vérifier
     * @return bool True si la vente est entièrement payée, false sinon
     */
    public function isFullyPaid(Sale $sale): bool
    {
        return $sale->paid_amount >= $sale->final_amount;
    }

    /**
     * Calcule le montant restant à payer
     * 
     * Retourne la différence entre le montant final et le montant déjà payé
     * La valeur ne peut pas être négative (max(0, ...))
     *
     * @param Sale $sale Vente concernée
     * @return float Montant restant à payer (0 si déjà entièrement payé ou dépassé)
     */
    public function getRemainingAmount(Sale $sale): float
    {
        return max(0, $sale->final_amount - $sale->paid_amount);
    }
}