<?php

namespace App\Http\Controllers;

use App\Models\CashRegisterSession;
use App\Models\Sale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Events\CashRegisterSessionOpened;
use App\Events\CashRegisterSessionClosed;
use Illuminate\Support\Facades\Auth;
use App\Services\CashRegisterSessionSummaryService;
use App\Services\SessionDiscrepancyService;
use Illuminate\Support\Carbon;
use App\Models\User;
use Illuminate\Validation\Rule;


class CashRegisterSessionController extends Controller
{
    protected $summaryService;
    protected $discrepancyService;

    public function __construct(
        CashRegisterSessionSummaryService $summaryService,
        SessionDiscrepancyService $discrepancyService
    ) {
        $this->summaryService = $summaryService;
        $this->discrepancyService = $discrepancyService;
    }

    private function userIsManager(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        $managerRoles = ['gerant', 'gérant'];
        return collect($managerRoles)->contains(fn($role) => $user->hasRole($role, 'api'));
    }

    /**
     * Display a listing of the cash register sessions.
     * Optional query parameter 'status' can be 'open' or 'closed' to filter sessions.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (!$user || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $query = CashRegisterSession::query();

        $isManager = $this->userIsManager($user);
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Admin can see all or filter by query param
        if ($isAdmin) {
            $requestedPosId = $request->query('point_of_sale_id');
            if ($requestedPosId) {
                $query->whereHas('cashRegister', fn($q) => $q->where('point_of_sale_id', $requestedPosId));
            }
        }
        // Managers (gerant) and regular users are restricted by their active POS or user_id
        else {
            if (!$activePosId) {
                // For non-admins, an active POS is mandatory
                return response()->json([
                    'success' => false,
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            // Check if user is assigned to the active POS
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            // Filter by active POS
            $query->whereHas('cashRegister', fn($q) => $q->where('point_of_sale_id', $activePosId));

            // Further restrict non-managers to their own sessions if they are not admin
            if (!$isAdmin && !$isManager) {
                $query->where('user_id', $user->id);
            }
        }

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($request->has('status')) {
            if ($request->status === 'open') {
                $query->where('is_closed', false);
            } elseif ($request->status === 'closed') {
                $query->where('is_closed', true);
            }
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->with(['cashRegister', 'user', 'transactions', 'discrepancies'])->get();

        return response()->json($sessions);
    }

    /**
     * Store a newly created cash register session (open session).
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        // 1. Vérification des permissions de base
        if (!$user || !$user->hasPermissionTo('create.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        // 2. Restriction métier : Les gérants ne peuvent pas ouvrir de session eux-mêmes
        if ($this->userIsManager($user)) {
            abort(403, 'Les gérants ne peuvent pas créer de session de caisse.');
        }

        $activePosId = $request->attributes->get('activePosId');

        if (!$activePosId) {
             return response()->json([
                'message' => 'Point de vente actif non défini pour l\'utilisateur.'
            ], 403);
        }
        // Check if user is assigned to the active POS
        if (!$user->pointsOfSale->contains($activePosId)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé pour ce point de vente.'
            ], 403);
        }

        // 3. Validation stricte
        $validated = $request->validate([
            'cash_register_id' => [
                'required',
                // On vérifie que la caisse existe ET appartient au même Point de Vente actif de l'utilisateur
                Rule::exists('cash_registers', 'id')->where(function ($query) use ($activePosId) {
                    $query->where('point_of_sale_id', $activePosId);
                }),
            ],
            'starting_amount' => 'required|numeric|min:0',
            'expected_cash_amount' => 'nullable|numeric|min:0',
            'start_ticket_number' => 'nullable|integer|min:0',
        ]);

        // 4. Sécurité : On force l'user_id à être celui de l'utilisateur connecté
        $userId = $user->id;

        // 5. Vérification anti-doublon (Caisse déjà occupée ?)
        $openSession = CashRegisterSession::where('cash_register_id', $validated['cash_register_id'])
            ->where('is_closed', false)
            ->first();

        if ($openSession) {
            return response()->json([
                'message' => 'There is already an open session for this cash register.'
            ], Response::HTTP_CONFLICT);
        }

        // 6. Préparation du montant attendu
        $expectedAmount = $validated['expected_cash_amount'] ?? $validated['starting_amount'];

        // 7. Création de la session
        $session = CashRegisterSession::create([
            'cash_register_id' => $validated['cash_register_id'],
            'user_id' => $userId,
            'starting_amount' => $validated['starting_amount'],
            'expected_cash_amount' => $expectedAmount,
            'start_ticket_number' => $validated['start_ticket_number'] ?? null,
            'is_closed' => false,
            'opened_at' => now(),
        ]);

        // 8. Déclenchement de l'événement (pour les logs, notifications ou matériel)
        event(new CashRegisterSessionOpened($session));

        return response()->json($session, Response::HTTP_CREATED);
    }

    /**
     * Display the specified cash register session.
     */
    public function show($id, Request $request)
    {
        try {
            $query = CashRegisterSession::with(['cashRegister', 'user', 'transactions', 'discrepancies']);
            if ($request->boolean('with_trashed')) {
                $query->withTrashed();
            }
            $session = $query->find($id);
            if (!$session) {
                return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
            }
            $user = auth()->user();
            if (!$user || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
                abort(403, 'This action is unauthorized.');
            }

            $isManager = $this->userIsManager($user);
            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            \Log::info("DEBUG SHOW: SessionID: $id, ActivePosID: $activePosId, SessionPosID: " . optional($session->cashRegister)->point_of_sale_id);

            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json([
                        'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                    ], 403);
                }
                // Check if user is assigned to the active POS
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé pour ce point de vente.'
                    ], 403);
                }
                // Check if session belongs to the active POS
                if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                    ], 403);
                }
                // Restriction retirée : permet aux autorisés de voir toutes les sessions du POS actif
            }

            // Calcul dynamique du montant théorique attendu
            $session->expected_cash_amount = $session->starting_amount + $session->total_sales - $session->total_refunds;

            return response()->json($session);
        } catch (\Exception $e) {
            \Log::error('Exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified cash register session.
     * This can be used to close the session by setting is_closed, actual_cash_amount, and closed_at.
     */
    public function update(Request $request, $id)
    {
        $session = CashRegisterSession::find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->user(); // Use default auth (sanctum)

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Autorisation : Admin peut tout faire, le caissier peut modifier SA propre session,
        // le gérant est bloqué par la restriction métier plus bas.
        $isOwner = $session->user_id === $user->id;
        $hasUpdatePerm = $user->hasPermissionTo('update.cash_register_sessions', 'api');
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');


        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            // Check if user is assigned to the active POS
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            // Ensure session belongs to the active POS
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
            if (!$hasUpdatePerm && !$isOwner) {
                abort(403, 'This action is unauthorized.');
            }
       }

        \Log::info("Session Update Request for ID {$id}: ", $request->all());

        $validated = $request->validate([
            'actual_cash_amount' => 'nullable|numeric|min:0',
            'expected_cash_amount' => 'nullable|numeric|min:0',
            'is_closed' => 'nullable|boolean',
            'is_bill_checked' => 'nullable|boolean',
            'closed_at' => 'nullable|date',
            'start_ticket_number' => 'nullable|integer|min:0',
        ]);

        $closedAt = null;
        if (isset($validated['closed_at'])) {
            try {
                $closedAt = Carbon::parse($validated['closed_at'])->timezone(config('app.timezone'))->toDateTimeString();
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid closed_at format. Expected a valid date string.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (isset($validated['is_closed']) && $validated['is_closed'] === true) {
            // Closing the session requires actual_cash_amount and closed_at
            if (!array_key_exists('actual_cash_amount', $validated) || $closedAt === null) {
                return response()->json([
                    'message' => 'To close the session, actual_cash_amount and closed_at are required.'
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $session->fill($validated);
            $session->closed_at = $closedAt;
            $session->is_closed = true;
            $session->save();

            event(new CashRegisterSessionClosed($session));

            return response()->json($session);
        } else {
            // Update provided fields (actual_cash_amount, is_bill_checked, etc.)
            $session->fill($validated);

            if ($closedAt !== null) {
                $session->closed_at = $closedAt;
            }

            $session->save();

            \Log::info("Session {$id} updated. New actual_cash_amount: " . $session->actual_cash_amount);

            return response()->json($session);
        }
    }

    /**
     * Soft delete the specified cash register session.
     */
    public function destroy($id, Request $request)
    {
        $session = CashRegisterSession::find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('delete.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }
        if (!$user->isAdmin() && $this->userIsManager($user)) {
            abort(403, 'Les gérants ne peuvent pas supprimer une session de caisse.');
        }

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            // Check if user is assigned to the active POS
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            // Ensure session belongs to the active POS
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
        }

        $session->delete();

        return response()->json(['message' => 'Cash register session deleted successfully.']);
    }

    /**
     * Reopen a closed cash register session.
     */
    public function reopen($id, Request $request)
    {
        $session = CashRegisterSession::find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('update.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }
        if (!$user->isAdmin() && $this->userIsManager($user)) {
            abort(403, 'Les gérants ne peuvent pas rouvrir une session de caisse.');
        }

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            // Check if user is assigned to the active POS
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            // Ensure session belongs to the active POS
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
        }

        if (!$session->is_closed) {
            return response()->json(['message' => 'Session is already open.'], Response::HTTP_BAD_REQUEST);
        }

        $session->is_closed = false;
        $session->closed_at = null;
        $session->actual_cash_amount = null;
        $session->save();

        return response()->json($session);
    }

    /**
     * List discrepancies for a cash register session.
     */
    public function listDiscrepancies(Request $request, $id)
    {
        $session = CashRegisterSession::with('discrepancies')->find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = auth()->guard('api')->user();
        if (!auth()->guard('api')->check() || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        // Apply active POS filtering for non-admins
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
        }

        return response()->json($session->discrepancies);
    }

    /**
     * Add a discrepancy to a cash register session.
     */
    public function addDiscrepancy(Request $request, $id)
    {
        $session = CashRegisterSession::find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();
        if (!$user->hasPermissionTo('update.cash_register_sessions', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        // Apply active POS filtering for non-admins
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
        }

        $validated = $request->validate([
            'description' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        $discrepancy = $this->discrepancyService->recordDiscrepancy(
            $session,
            $validated['amount'],
            $validated['description']
        );

        return response()->json($discrepancy, Response::HTTP_CREATED);
    }

    /**
     * Get a summary report of the cash register session.
     */
    public function summary($id, Request $request)
    {
        // 1. Chargement de la session avec sa caisse pour connaître son POS
        $session = CashRegisterSession::with(['cashRegister', 'transactions', 'discrepancies', 'user'])
            ->find($id);

        if (!$session) {
            return response()->json(['message' => 'Cash register session not found.'], Response::HTTP_NOT_FOUND);
        }

        /** @var \App\Models\User|null $user */
        $user = auth()->guard('api')->user();

        // 2. Vérification des permissions (Doit avoir le droit de voir les sessions)
        if (!$user || !$user->hasPermissionTo('view.cash_register_sessions', 'api')) {
            abort(403, 'Action non autorisée.');
        }

        // 3. LOGIQUE DE FILTRAGE PAR POINT DE VENTE (POS)
        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            // Ensure session belongs to the active POS
            if (optional($session->cashRegister)->point_of_sale_id !== $activePosId) {
                 return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé : la session n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
        }
        // Si c'est un Admin, le code continue sans bloquer (Accès total)

        // 4. Sécurité métier : La session doit être fermée pour voir le résumé final
        if (!$session->is_closed) {
            return response()->json([
                'message' => 'Le résumé ne peut être consulté que pour une session fermée.'
            ], Response::HTTP_CONFLICT);
        }

        // 5. Utilisation du Service (qui gère déjà le masquage de l'écart de caisse pour le gérant)
        $summary = $this->summaryService->build($session);

        return response()->json($summary);
    }

    /**
     * Get the status of a specific cash register session.
     */
    /**
     * Get the status of a specific cash register session.
     */
    public function status($cashRegisterId, Request $request)
    {
        try {
            // Vérifier que l'utilisateur est authentifié
            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Non authentifié'
                ], 401);
            }

            // Apply active POS filtering for non-admins
            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json([
                        'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                    ], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé pour ce point de vente.'
                    ], 403);
                }
                // Ensure the cash register belongs to the active POS
                $cashRegister = \App\Models\CashRegister::find($cashRegisterId); // Fetch to get POS ID
                if (!$cashRegister || $cashRegister->point_of_sale_id !== $activePosId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé : la caisse n\'appartient pas à votre point de vente actif.'
                    ], 403);
                }
            }


            // Rechercher la session ouverte pour cette caisse
            $session = CashRegisterSession::where('cash_register_id', $cashRegisterId)
                ->where('is_closed', false)
                ->latest('opened_at')
                ->first();

            if ($session) {
                return response()->json([
                    'status' => 'in_use',
                    'in_use' => true,
                    'opened_at' => $session->opened_at,
                    'user' => $session->user->name,
                    'session_id' => $session->id,
                    'cash_register_id' => $session->cash_register_id
                ]);
            } else {
                return response()->json([
                    'status' => 'available',
                    'in_use' => false,
                    'message' => 'Cette caisse est libre, aucune session active.'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Erreur dans status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Get the active session for the current user.
     */
    public function myActiveSession(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'data' => null,
                    'message' => 'Utilisateur non authentifié'
                ], 401);
            }

            $activePosId = $request->attributes->get('activePosId');
            if (!$activePosId) {
                return response()->json([
                    'data' => null,
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }

            // Rechercher la session active pour l'utilisateur connecté dans le POS actif
            $session = CashRegisterSession::where('user_id', $user->id)
                ->where('is_closed', false)
                ->whereHas('cashRegister', fn($q) => $q->where('point_of_sale_id', $activePosId))
                ->with(['cashRegister', 'user'])
                ->first();

            if ($session) {
                return response()->json([
                    'data' => $session,
                    'has_active_session' => true
                ]);
            }

            return response()->json([
                'data' => null,
                'has_active_session' => false,
                'message' => 'Aucune session active'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur dans myActiveSession: ' . $e->getMessage());
            return response()->json([
                'data' => null,
                'has_active_session' => false,
                'message' => 'Erreur serveur'
            ], 500);
        }
    }
    public function openSessions(Request $request)
    {
        $user = $request->user();
        $activePosId = $request->attributes->get('activePosId');

        if (!$activePosId) {
             return response()->json([
                'message' => 'Point de vente actif non défini pour l\'utilisateur.'
            ], 403);
        }
        // Check if user is assigned to the active POS
        if (!$user->pointsOfSale->contains($activePosId)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé pour ce point de vente.'
            ], 403);
        }

        $sessions = CashRegisterSession::whereNull('closed_at')
            ->whereHas('cashRegister', function ($q) use ($activePosId) {
                $q->where('point_of_sale_id', $activePosId);
            })
            ->with(['cashRegister', 'user'])
            ->get();
        return response()->json($sessions);
    }

}
