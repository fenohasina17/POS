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
     * @param Sale $sale
     * @param array $paymentsData  Format : [['payment_id' => int, 'amount' => float, 'reference' => ?string, 'notes' => ?string, 'discount_percentage' => ?float], ...]
     * @param float|null $forcedChangeAmount  (optionnel) Rendu monnaie forcé
     * @return array [total_paid_now, new_total_paid, change_amount, is_completed, new_final_amount]
     * @throws InvalidArgumentException
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
        $newDiscount = $sale->discount_percentage; // Garde la remise existante

        DB::transaction(function () use ($sale, $paymentsData, &$totalPaidNow, &$newDiscount) {
            foreach ($paymentsData as $data) {
                if (!isset($data['payment_id']) || !isset($data['amount']) || $data['amount'] <= 0) {
                    throw new InvalidArgumentException("Chaque paiement doit avoir un payment_id et un amount > 0.");
                }

                // ✅ Vérifier si une remise est fournie dans ce paiement
                if (isset($data['discount_percentage']) && $data['discount_percentage'] > 0) {
                    // Appliquer la nouvelle remise à la vente
                    $newDiscount = $data['discount_percentage'];
                    
                    // Recalculer le montant final avec la nouvelle remise
                    $newFinalAmount = round($sale->total_amount * (1 - ($newDiscount / 100)), 2);
                    
                    // Mettre à jour la vente avec la nouvelle remise
                    $sale->update([
                        'discount_percentage' => $newDiscount,
                        'final_amount' => $newFinalAmount,
                    ]);
                    
                    // Rafraîchir la vente pour avoir la nouvelle valeur
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
            $finalAmount   = $sale->final_amount; // Déjà recalculé si remise modifiée

            // ✅ Calcul correct de la monnaie
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
            'new_final_amount' => $sale->final_amount, // Ajouté pour info
            'new_discount'     => $sale->discount_percentage, // Ajouté pour info
        ];
    }

    /**
     * Version simplifiée qui retourne directement le résultat formaté pour l'API
     */
    public function addPaymentsAndFormatResponse(Sale $sale, array $paymentsData, ?float $forcedChangeAmount = null): array
    {
        $result = $this->addPayments($sale, $paymentsData, $forcedChangeAmount);
        
        $sale->load('payments.payment');
        
        return [
            'message' => 'Paiement(s) enregistré(s)',
            'sale' => $sale,
            'total_paid_now' => $result['total_paid_now'],
            'total_paid' => $result['new_total_paid'],
            'remaining' => $result['remaining'],
            'change' => $result['change_amount'],
            'is_completed' => $result['is_completed'],
            'discount_applied' => $result['new_discount'],
        ];
    }

    /**
     * Calcule si une vente est complètement payée
     */
    public function isFullyPaid(Sale $sale): bool
    {
        return $sale->paid_amount >= $sale->final_amount;
    }

    /**
     * Calcule le montant restant à payer
     */
    public function getRemainingAmount(Sale $sale): float
    {
        return max(0, $sale->final_amount - $sale->paid_amount);
    }
}