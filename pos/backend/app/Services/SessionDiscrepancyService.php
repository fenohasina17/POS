<?php

namespace App\Services;

use App\Models\CashRegisterSession;
use App\Models\SessionDiscrepancy;
use Illuminate\Support\Facades\DB;

class SessionDiscrepancyService
{
    /**
     * Enregistre un écart pour une session donnée.
     *
     * @param CashRegisterSession $session
     * @param float $amount
     * @param string $explanation
     * @return SessionDiscrepancy
     */
    public function recordDiscrepancy(CashRegisterSession $session, float $amount, string $explanation): SessionDiscrepancy
    {
        return DB::transaction(function () use ($session, $amount, $explanation) {
            // Création de l'écart
            $discrepancy = $session->discrepancies()->create([
                'difference_amount' => $amount,
                'explanation' => $explanation,
                'is_checked' => false,
            ]);

            // Mise à jour de la session pour marquer qu'elle a un écart
            $session->update([
                'has_discrepancy' => true,
                'difference_amount' => $amount,
                'discrepancy_explanation' => $explanation,
            ]);

            return $discrepancy;
        });
    }

    /**
     * Valide un écart (par un gérant).
     *
     * @param SessionDiscrepancy $discrepancy
     * @return bool
     */
    public function markAsChecked(SessionDiscrepancy $discrepancy): bool
    {
        return $discrepancy->update(['is_checked' => true]);
    }

    /**
     * Récupère les écarts non traités pour un point de vente.
     *
     * @param int|null $pointOfSaleId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUncheckedDiscrepancies(int $pointOfSaleId = null)
    {
        $query = SessionDiscrepancy::where('is_checked', false)
            ->with(['session.cashRegister', 'session.user']);

        if ($pointOfSaleId) {
            $query->whereHas('session.cashRegister', function ($q) use ($pointOfSaleId) {
                $q->where('point_of_sale_id', $pointOfSaleId);
            });
        }

        return $query->latest()->get();
    }
}
