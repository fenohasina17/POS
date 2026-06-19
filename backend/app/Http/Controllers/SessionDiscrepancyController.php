<?php

namespace App\Http\Controllers;

use App\Models\SessionDiscrepancy;
use App\Services\SessionDiscrepancyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SessionDiscrepancyController extends Controller
{
    protected $service;

    public function __construct(SessionDiscrepancyService $service)
    {
        $this->service = $service;
    }

    /**
     * Liste des écarts filtrée par POS pour les gérants.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');
        
        // Admins can see all discrepancies or filter by query param
        if ($isAdmin) {
            $requestedPosId = $request->query('point_of_sale_id');
            if ($requestedPosId) {
                if (!$user->pointsOfSale->contains($requestedPosId)) {
                    return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                }
                $discrepancies = $this->service->getUncheckedDiscrepancies($requestedPosId);
            } else {
                // Admin sees all if no specific POS is requested
                $discrepancies = $this->service->getUncheckedDiscrepancies(null);
            }
        }
        // Non-admins (including managers) are restricted by their active POS
        else {
            if (!$activePosId) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
            }
            $discrepancies = $this->service->getUncheckedDiscrepancies($activePosId);
        }
        
        return response()->json($discrepancies);
    }

    /**
     * Valider un écart (le marquer comme vérifié).
     */
    public function check($id, Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        if (!$user->hasRole(['admin', 'gerant'], 'api')) {
            abort(403, 'Seuls les administrateurs et gérants peuvent valider les écarts.');
        }

        $discrepancy = SessionDiscrepancy::with('cashRegisterSession.cashRegister')->findOrFail($id);
        
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin (managers) specific checks
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
            }
            // Ensure discrepancy belongs to the active POS
            if (optional($discrepancy->cashRegisterSession->cashRegister)->point_of_sale_id !== $activePosId) {
                return response()->json(['message' => 'Accès refusé : l\'écart n\'appartient pas à votre point de vente actif.'], 403);
            }
        }

        $this->service->markAsChecked($discrepancy);

        return response()->json([
            'message' => 'Écart validé avec succès.',
            'data' => $discrepancy
        ]);
    }
}
