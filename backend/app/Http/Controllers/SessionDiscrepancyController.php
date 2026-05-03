<?php

namespace App\Http\Controllers;

use App\Models\SessionDiscrepancy;
use App\Services\SessionDiscrepancyService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
        $user = auth()->user();
        
        // Si gérant, filtrer par son POS
        $pointOfSaleId = null;
        if (!$user->hasRole('admin', 'api')) {
            $pointOfSaleId = $user->point_of_sale_id;
        }

        $discrepancies = $this->service->getUncheckedDiscrepancies($pointOfSaleId);
        
        return response()->json($discrepancies);
    }

    /**
     * Valider un écart (le marquer comme vérifié).
     */
    public function check($id)
    {
        $user = auth()->user();
        if (!$user->hasRole(['admin', 'gerant'], 'api')) {
            abort(403, 'Seuls les administrateurs et gérants peuvent valider les écarts.');
        }

        $discrepancy = SessionDiscrepancy::findOrFail($id);
        
        $this->service->markAsChecked($discrepancy);

        return response()->json([
            'message' => 'Écart validé avec succès.',
            'data' => $discrepancy
        ]);
    }
}
