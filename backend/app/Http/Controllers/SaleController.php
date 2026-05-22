<?php
// app/Http/Controllers/SaleController.php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\OrderLine;
use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Services\SaleService;
use App\Services\PrintGroupingService;
use App\Services\CashTransactionService;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    protected SaleService $saleService;
    protected ?PrintGroupingService $printGroupingService = null;
    protected $cashTransactionService;
    /**
     * Constructeur du contrôleur
     *
     * @param SaleService $saleService Service de gestion des ventes
     * @param PrintGroupingService|null $printGroupingService Service de regroupement pour impression (optionnel)
     */
    public function __construct(SaleService $saleService, ?PrintGroupingService $printGroupingService = null, CashTransactionService $cashTransactionService)
    {
        $this->cashTransactionService = $cashTransactionService;
        $this->saleService = $saleService;
        $this->printGroupingService = $printGroupingService;
    }

    /**
     * POST /api/sales/{saleId}/validate
     *
     * Valide une commande en attente (pending) et la transforme en vente complète
     *
     * @param Request $request Requête HTTP contenant :
     *                         REQUIS :
     *                         - payment_id (int) : ID du mode de paiement (existe dans payments)
     *                         OPTIONNELS :
     *                         - discount_percentage (float|numeric) : Remise en % (min:0, max:100)
     *                         - amount_received (float|numeric) : Montant reçu (min:0, par défaut = final_amount)
     *                         - change_amount (float|numeric) : Monnaie rendue (min:0, calculé auto si absent)
     * @param int|string $saleId ID de la commande à valider
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : {
     *             success: true,
     *             message: "Commande validée avec succès",
     *             data: array,      // Données formatées par catégorie (sale, categories, totals, payments)
     *             print_groups: array // Groupes pour impression (optionnel)
     *         }
     *         ERREURS :
     *         - 404 : Commande non trouvée
     *         - 422 : Erreur de validation (paramètres invalides)
     *         - 500 : Erreur serveur
     */
    public function validatePendingOrder(Request $request, $saleId)
    {
        try {
            $validated = $request->validate([
                'payment_id' => 'required_without:payments|exists:payments,id',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'amount_received' => 'nullable|numeric|min:0',
                'change_amount' => 'nullable|numeric|min:0',
                'payments' => 'nullable|array',
                'payments.*.payment_id' => 'required|exists:payments,id',
                'payments.*.amount' => 'required|numeric|min:0',
                'payments.*.reference' => 'nullable|string',
                'payments.*.notes' => 'nullable|string',
            ]);

            $sale = Sale::findOrFail($saleId);
            $validatedSale = $this->saleService->validatePendingOrder($sale, $validated);

            $formattedData = $this->saleService->getFormattedSaleData($validatedSale);

            $printGroups = [];
            if (isset($this->printGroupingService)) {
                $printGroups = $this->printGroupingService->preparePrintData($validatedSale);
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande validée avec succès',
                'data' => $formattedData,
                'print_groups' => $printGroups
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Commande non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/sales
     *
     * Liste toutes les ventes avec filtres
     * - Admin : voit toutes les ventes
     * - Gérant : voit uniquement les ventes de son point de vente
     * - Gérant : peut filtrer par session de caisse (uniquement celles de son point de vente)
     * - Gérant : peut filtrer par utilisateur (uniquement ceux de son point de vente)
     * - Caissier : voit uniquement ses propres ventes
     *
     * @param Request $request Requête HTTP avec paramètres query :
     *                         OPTIONNELS :
     *                         - cash_register_session_id (int) : Filtrer par session de caisse
     *                         - user_id (int) : Filtrer par utilisateur (caissier)
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Collection de ventes avec relations (user, orderLines.product, payments.payment)
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée ou session/user n'appartient pas au point de vente du gérant
     *         - 404 : Session de caisse non trouvée
     *         - 500 : Erreur serveur
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            if (!$user->hasPermissionTo('view.sales', 'api')) {
                return response()->json(['message' => 'Vous n\'avez pas la permission de voir les ventes.'], 403);
            }

            $sessionId = $request->query('cash_register_session_id');
            $userId = $request->query('user_id');

            // Charger les relations : utilisateur, lignes, produits, paiements + méthode de paiement
            $sales = Sale::with([
                'user',
                'orderlines.product',
                'payments.payment'  // ← relation payments.payment (SalePayment -> Payment)
            ]);

            $isAdmin = $user->hasRole('admin', 'api');
            $isManager = $user->hasAnyRole(['gerant', 'gérant'], 'api');
            $activePosId = $request->attributes->get('activePosId');

            // ========== RESTRICTIONS ==========
            if (!$isAdmin) {
                if (!$activePosId) {
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
                $sales->where('point_of_sale_id', $activePosId);

                if (!$isManager) {
                    // Caissier : voit uniquement ses propres ventes
                    $sales->where('user_id', $user->id);
                }

                if ($sessionId) {
                    // Vérifier que la session appartient au POS actif
                    $session = CashRegisterSession::where('id', $sessionId)
                        ->whereHas('cashRegister', fn($q) => $q->where('point_of_sale_id', $activePosId))
                        ->first();
                    if (!$session) {
                        return response()->json(['message' => 'Session non trouvée ou accès non autorisé.'], 403);
                    }
                    $sales->where('cash_register_session_id', $sessionId);
                }

                if ($userId) {
                    // Vérifier que l'utilisateur cible est rattaché au POS actif
                    $targetUser = User::where('id', $userId)
                        ->whereHas('pointsOfSale', fn($q) => $q->where('point_of_sales.id', $activePosId))
                        ->first();
                    if (!$targetUser) {
                        return response()->json(['message' => 'Utilisateur non trouvé ou accès non autorisé.'], 403);
                    }
                    // Pour les non-managers, s'assurer que l'utilisateur cible est l'utilisateur connecté
                    if (!$isManager && (int)$userId !== (int)$user->id) {
                         return response()->json(['message' => 'Vous ne pouvez voir que vos propres ventes.'], 403);
                    }
                    $sales->where('user_id', $userId);
                }
            } else { // Admin
                if ($sessionId && !CashRegisterSession::where('id', $sessionId)->exists()) {
                    return response()->json(['message' => 'Session non trouvée.'], 404);
                }
                if ($sessionId) {
                    $sales->where('cash_register_session_id', $sessionId);
                }
                if ($userId) {
                    $sales->where('user_id', $userId);
                }
                // Admin can filter by point_of_sale_id in query
                $requestedPosId = $request->query('point_of_sale_id');
                if ($requestedPosId) {
                    if (!$user->pointsOfSale->contains($requestedPosId)) {
                        return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
                    }
                    $sales->where('point_of_sale_id', $requestedPosId);
                } elseif ($activePosId) {
                    // If no requested POS, filter by active POS if set
                    $sales->where('point_of_sale_id', $activePosId);
                }
            }

            $sales = $sales->orderByDesc('created_at')->get();

            return response()->json($sales);

        } catch (\Exception $e) {
            \Log::error('Erreur dans SalesController@index : ' . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la récupération des ventes.'], 500);
        }
    }
    /**
     * GET /api/point-of-sales/{pointOfSale}/kpis
     *
     * Récupère les KPIs produits pour un point de vente (quantités vendues et chiffre d'affaires par produit)
     *
     * @param PointOfSale $pointOfSale Instance du point de vente (route model binding)
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Collection avec pour chaque produit :
     *                         - name (string) : Nom du produit
     *                         - total_quantity (int) : Quantité totale vendue
     *                         - total_revenue (float) : Chiffre d'affaires généré
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée ou accès restreint (gérant ne peut voir que son point de vente)
     */
    public function productKpis(PointOfSale $pointOfSale, Request $request)
    {
        $user = auth()->user();

        if (!$user->hasPermissionTo('view.sales', 'api')) {
            return response()->json(['message' => 'Vous n\'avez pas la permission de voir les KPIs.'], 403);
        }

        $isAdmin = $user->hasRole('admin', 'api');
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin users (including managers) must be associated with the requested pointOfSale, or it must be their active POS
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json(['message' => 'Point de vente actif non défini pour l\'utilisateur.'], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json(['message' => 'Accès refusé pour ce point de vente.'], 403);
            }
            // Ensure the requested pointOfSale matches the active POS
            if ((int)$pointOfSale->id !== (int)$activePosId) {
                return response()->json(['message' => 'Accès refusé : Ce point de vente ne correspond pas à votre point de vente actif.'], 403);
            }
        }

        $kpis = OrderLine::whereHas('sale', function ($query) use ($pointOfSale) {
            $query->where('point_of_sale_id', $pointOfSale->id)
                ->where('status', 'completed');
        })
            ->with('product')
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_revenue')
            )
            ->groupBy('product_id')
            ->get()
            ->map(function ($orderLine) {
                return [
                    'name' => $orderLine->product->name,
                    'total_quantity' => $orderLine->total_quantity,
                    'total_revenue' => $orderLine->total_revenue,
                ];
            });

        return response()->json($kpis);
    }

    /**
     * GET /api/sales/monthly/{pointOfSaleId}
     *
     * Récupère les statistiques mensuelles et journalières des ventes pour un point de vente
     *
     * @param Request $request Requête HTTP avec paramètres query :
     *                         OPTIONNELS :
     *                         - year (int) : Année (défaut: année courante)
     *                         - start_date (string|date) : Date de début (format Y-m-d)
     *                         - end_date (string|date) : Date de fin (format Y-m-d)
     *                         - statuses (string|array) : Statuts à inclure (ex: 'completed')
     *                         - payment_ids (string|array) : IDs des modes de paiement
     * @param int|string $pointOfSaleId ID du point de vente
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : {
     *             data: [{
     *                 period: string (Y-m),
     *                 label: string (ex: "Janvier 2024"),
     *                 total_sales: int,
     *                 transactions: int,
     *                 average_ticket: int,
     *                 cash_sales: int,
     *                 cash_transactions: int,
     *                 daily_breakdown: [{
     *                     date: string,
     *                     label: string,
     *                     transactions: int,
     *                     total_sales: int
     *                 }]
     *             }],
     *             meta: {
     *                 year: int,
     *                 point_of_sale_id: int,
     *                 filters: {start_date: string, end_date: string},
     *                 overall: {
     *                     total_sales: int,
     *                     transactions: int,
     *                     average_ticket: int,
     *                     cash_sales: int,
     *                     cash_transactions: int
     *                 }
     *             }
     *         }
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée (seuls admin et gérants)
     *         - 422 : Format de date invalide
     */
    public function monthlySales(Request $request, $pointOfSaleId)
    {
        $user = auth()->guard('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        if (!$user->hasPermissionTo('view.sales', 'api')) {
            return response()->json(['message' => 'Vous n\'avez pas la permission de voir les statistiques.'], 403);
        }

        $isAdmin = $user->hasRole('admin', 'api');
        $isManager = $user->hasAnyRole(['gerant', 'gérant'], 'api');

        if (!$isAdmin) {
            if ($isManager) {
                $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                if (empty($assignedPosIds) && $user->point_of_sale_id) $assignedPosIds = [$user->point_of_sale_id];

                if (!in_array((int)$pointOfSaleId, $assignedPosIds)) {
                    return response()->json(['message' => 'Vous ne pouvez consulter que les statistiques de vos points de vente.'], 403);
                }
            } else {
                return response()->json(['message' => 'Seuls les administrateurs ou gérants peuvent consulter ces statistiques.'], 403);
            }
        }

        $year = (int) $request->query('year', now()->year);
        $startDateInput = $request->query('start_date');
        $endDateInput = $request->query('end_date');

        try {
            $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : Carbon::create($year, 1, 1)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Le format de la date de début est invalide.'], 422);
        }

        try {
            $endDate = $endDateInput ? Carbon::parse($endDateInput)->endOfDay() : Carbon::create($year, 12, 31)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Le format de la date de fin est invalide.'], 422);
        }

        if ($endDate->lt($startDate)) {
            return response()->json(['message' => 'La date de fin doit être postérieure ou égale à la date de début.'], 422);
        }

        $saleTable = (new Sale())->getTable();
        $hasStatusColumn = Schema::hasColumn($saleTable, 'status');
        $hasPaymentColumn = Schema::hasColumn($saleTable, 'payment_id');

        $statusesInput = Arr::wrap($request->query('statuses', $request->query('status', [])));
        $statusesFilter = collect($statusesInput)->flatMap(function ($value) {
            if (is_array($value))
                return $value;
            if (is_string($value))
                return preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            return [$value];
        })->map(fn($value) => trim((string) $value))->filter()->unique()->values();

        $paymentInput = Arr::wrap($request->query('payment_ids', $request->query('payment_id', [])));
        $paymentFilter = collect($paymentInput)->flatMap(function ($value) {
            if (is_array($value))
                return $value;
            if (is_string($value))
                return preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            return [$value];
        })->map(fn($value) => (int) $value)->filter(fn($value) => $value > 0)->unique()->values();

        $salesQuery = Sale::query()
            ->where("{$saleTable}.point_of_sale_id", $pointOfSaleId)
            ->whereBetween("{$saleTable}.created_at", [$startDate, $endDate]);

        if ($hasStatusColumn) {
            if ($statusesFilter->isNotEmpty()) {
                $salesQuery->whereIn('status', $statusesFilter->all());
            } else {
                $salesQuery->where(function ($inner) {
                    $inner->whereNull('status')->orWhereNotIn('status', ['cancelled', 'canceled']);
                });
            }
        }

        if ($paymentFilter->isNotEmpty() && $hasPaymentColumn) {
            $salesQuery->whereIn('payment_id', $paymentFilter->all());
        }

        Carbon::setLocale(app()->getLocale() ?? 'fr');
        $cashRegex = "(cash|esp|espe|espec|liquid|liquide|argent)";

        $monthlySelect = [
            DB::raw("DATE_FORMAT({$saleTable}.created_at, '%Y-%m') as period"),
            DB::raw('COUNT(*) as transactions'),
            DB::raw("SUM(COALESCE({$saleTable}.final_amount, {$saleTable}.total_amount, 0)) as total_sales"),
        ];

        if ($hasPaymentColumn) {
            $monthlySelect[] = DB::raw("SUM(CASE WHEN LOWER(COALESCE(payments.name, '')) REGEXP '{$cashRegex}' THEN 1 ELSE 0 END) as cash_transactions");
            $monthlySelect[] = DB::raw("SUM(CASE WHEN LOWER(COALESCE(payments.name, '')) REGEXP '{$cashRegex}' THEN COALESCE({$saleTable}.final_amount, {$saleTable}.total_amount, 0) ELSE 0 END) as cash_sales");
        } else {
            $monthlySelect[] = DB::raw('0 as cash_transactions');
            $monthlySelect[] = DB::raw('0 as cash_sales');
        }

        $monthlyBuilder = (clone $salesQuery)->select($monthlySelect);
        if ($hasPaymentColumn) {
            $monthlyBuilder->leftJoin('payments', "{$saleTable}.payment_id", '=', 'payments.id');
        }

        $monthlyQuery = $monthlyBuilder->groupBy(DB::raw("DATE_FORMAT({$saleTable}.created_at, '%Y-%m')"))->orderBy('period')->get();

        $dailySelect = [
            DB::raw("DATE_FORMAT({$saleTable}.created_at, '%Y-%m') as period"),
            DB::raw("DATE({$saleTable}.created_at) as day_date"),
            DB::raw('COUNT(*) as transactions'),
            DB::raw("SUM(COALESCE({$saleTable}.final_amount, {$saleTable}.total_amount, 0)) as total_sales"),
        ];

        if ($hasPaymentColumn) {
            $dailySelect[] = DB::raw("SUM(CASE WHEN LOWER(COALESCE(payments.name, '')) REGEXP '{$cashRegex}' THEN 1 ELSE 0 END) as cash_transactions");
            $dailySelect[] = DB::raw("SUM(CASE WHEN LOWER(COALESCE(payments.name, '')) REGEXP '{$cashRegex}' THEN COALESCE({$saleTable}.final_amount, {$saleTable}.total_amount, 0) ELSE 0 END) as cash_sales");
        } else {
            $dailySelect[] = DB::raw('0 as cash_transactions');
            $dailySelect[] = DB::raw('0 as cash_sales');
        }

        $dailyBuilder = (clone $salesQuery)->select($dailySelect);
        if ($hasPaymentColumn) {
            $dailyBuilder->leftJoin('payments', "{$saleTable}.payment_id", '=', 'payments.id');
        }

        $dailyData = $dailyBuilder->groupBy(DB::raw("DATE_FORMAT({$saleTable}.created_at, '%Y-%m')"), DB::raw("DATE({$saleTable}.created_at)"))
            ->orderBy('period')->orderBy('day_date')->get()
            ->map(function ($row) {
                $date = Carbon::parse($row->day_date);
                return [
                    'period' => $row->period,
                    'date' => $date->toDateString(),
                    'label' => $date->translatedFormat('d MMM Y'),
                    'day' => $date->format('Y-m-d'),
                    'transactions' => (int) ($row->transactions ?? 0),
                    'total_sales' => (int) ($row->total_sales ?? 0),
                    'cash_sales' => (int) ($row->cash_sales ?? 0),
                    'cash_transactions' => (int) ($row->cash_transactions ?? 0),
                ];
            });

        $monthlyData = $monthlyQuery->map(function ($row) use ($dailyData) {
            try {
                $date = Carbon::createFromFormat('Y-m', $row->period)->startOfMonth();
                $label = $date->translatedFormat('F Y');
            } catch (\Throwable $e) {
                $label = $row->period;
            }

            $days = $dailyData->where('period', $row->period)->values()->map(function ($day) {
                $parsed = Carbon::parse($day['date']);
                return [
                    'period' => $day['period'],
                    'date' => $parsed->toDateString(),
                    'label' => $parsed->translatedFormat('d MMMM Y'),
                    'day' => $parsed->format('Y-m-d'),
                    'transactions' => $day['transactions'],
                    'total_sales' => $day['total_sales'],
                ];
            });

            $totalSales = (int) ($row->total_sales ?? 0);
            $transactions = (int) ($row->transactions ?? 0);

            return [
                'period' => $row->period,
                'label' => Str::title($label),
                'total_sales' => $totalSales,
                'transactions' => $transactions,
                'average_ticket' => $transactions > 0 ? (int) round($totalSales / max($transactions, 1)) : 0,
                'cash_sales' => (int) ($row->cash_sales ?? 0),
                'cash_transactions' => (int) ($row->cash_transactions ?? 0),
                'daily_breakdown' => $days,
            ];
        })->values();

        $overallTotalSales = (int) $monthlyData->sum('total_sales');
        $overallTransactions = (int) $monthlyData->sum('transactions');

        return response()->json([
            'data' => $monthlyData,
            'meta' => [
                'year' => $year,
                'point_of_sale_id' => (int) $pointOfSaleId,
                'filters' => ['start_date' => $startDate->toDateString(), 'end_date' => $endDate->toDateString()],
                'overall' => [
                    'total_sales' => $overallTotalSales,
                    'transactions' => $overallTransactions,
                    'average_ticket' => $overallTransactions > 0 ? (int) round($overallTotalSales / max($overallTransactions, 1)) : 0,
                    'cash_sales' => (int) $monthlyData->sum('cash_sales'),
                    'cash_transactions' => (int) $monthlyData->sum('cash_transactions'),
                ],
            ],
        ]);
    }

    /**
     * POST /api/sales
     *
     * Crée une vente complète (payée immédiatement)
     * Supporte les paiements uniques ou multiples
     *
     * @param Request $request Requête HTTP contenant :
     *                         REQUIS :
     *                         - user_id (int) : ID du caissier (existe dans users)
     *                         - point_of_sale_id (int) : ID du point de vente (existe dans point_of_sales)
     *                         - cash_register_session_id (int) : ID de la session caisse (existe dans cash_register_sessions)
     *                         - total_amount (float|numeric) : Montant total avant remise (min:0)
     *                         - final_amount (float|numeric) : Montant final après remise (min:0)
     *                         - status (string) : 'pending', 'completed' ou 'cancelled'
     *                         - items (array) : Articles vendus (min:1)
     *                           - items.*.product_id (int) : ID produit (existe dans products)
     *                           - items.*.quantity (int) : Quantité (min:1)
     *                           - items.*.unit_price (float) : Prix unitaire (min:0)
     *                           - items.*.total (float) : Total ligne (min:0)
     *                         OPTIONNELS :
     *                         - table_id (int|null) : ID de la table (existe dans tables, null = emporter)
     *                         - discount_percentage (float) : Remise en % (min:0, max:100)
     *                         - notes (string|null) : Notes optionnelles
     *
     *                         FORMAT PAIEMENT UNIQUE :
     *                         - payment_id (int) : ID du mode de paiement (existe dans payments)
     *                         - amount_received (float) : Montant reçu (min:0)
     *                         - change_returned (float|null) : Monnaie rendue (min:0)
     *
     *                         FORMAT PAIEMENTS MULTIPLES :
     *                         - payments (array) : Tableau des paiements (min:1)
     *                           - payments.*.payment_id (int) : ID du mode de paiement
     *                           - payments.*.amount (float) : Montant (min:0.01)
     *                           - payments.*.reference (string|null) : Référence (max:100)
     *                           - payments.*.notes (string|null) : Notes (max:255)
     *                         - change_amount (float|null) : Monnaie rendue (min:0)
     *
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (201) : {
     *             success: true,
     *             message: "Vente créée avec succès",
     *             data: array  // Données formatées par catégorie
     *         }
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée ou session fermée ou point de vente incorrect
     *         - 422 : Erreur de validation
     *         - 500 : Erreur serveur
     */
    public function store(Request $request)
    {

        $user = auth()->guard('api')->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        if (!$user->hasPermissionTo('create.sales', 'api')) {
            return response()->json(['message' => 'Vous n\'avez pas la permission de créer une vente.'], 403);
        }
        try {
            // Validation (inchangée)
            $baseRules = [
                'table_id' => 'nullable|exists:tables,id',
                'user_id' => 'required|exists:users,id',
                'point_of_sale_id' => 'required|exists:point_of_sales,id',
                'cash_register_session_id' => 'required|exists:cash_register_sessions,id',
                'total_amount' => 'required|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'final_amount' => 'required|numeric|min:0',
                'status' => 'required|in:pending,completed,cancelled',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.total' => 'required|numeric|min:0',
            ];

            if ($request->has('payments')) {
                $paymentRules = [
                    'payments' => 'required|array|min:1',
                    'payments.*.payment_id' => 'required|exists:payments,id',
                    'payments.*.amount' => 'required|numeric|min:0.01',
                    'payments.*.reference' => 'nullable|string|max:100',
                    'payments.*.notes' => 'nullable|string|max:255',
                    'amount_received' => 'sometimes|numeric|min:0',
                    'change_amount' => 'nullable|numeric|min:0',
                ];
                $rules = array_merge($baseRules, $paymentRules);
            } else {
                $singlePaymentRules = [
                    'payment_id' => 'required|exists:payments,id',
                    'amount_received' => 'required|numeric|min:0',
                    'change_returned' => 'nullable|numeric|min:0',
                ];
                $rules = array_merge($baseRules, $singlePaymentRules);
            }

            $validated = $request->validate($rules);

            $isAdmin = $user->hasRole('admin', 'api');
            if (!$isAdmin) {
                $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                if (empty($assignedPosIds) && $user->point_of_sale_id) $assignedPosIds = [$user->point_of_sale_id];

                if (!in_array((int)$validated['point_of_sale_id'], $assignedPosIds)) {
                    return response()->json(['message' => 'Vous ne pouvez créer des ventes que sur vos points de vente assignés.'], 403);
                }

                $session = CashRegisterSession::find($validated['cash_register_session_id']);
                if ($session && $session->is_closed) {
                    return response()->json(['message' => 'La session de caisse est fermée. Vous ne pouvez pas créer de vente.'], 422);
                }
            }

            // Préparer les données pour le service
            $saleData = $validated;

            if ($request->has('payments')) {
                $saleData['payments'] = $validated['payments'];
                $saleData['amount_received'] = collect($validated['payments'])->sum('amount');
                $saleData['change_amount'] = $validated['change_amount'] ?? max(0, $saleData['amount_received'] - $validated['final_amount']);
            } else {
                $saleData['payment_id'] = $validated['payment_id'];
                $saleData['amount_received'] = $validated['amount_received'];
                $saleData['change_amount'] = $validated['change_returned'] ?? max(0, $validated['amount_received'] - $validated['final_amount']);
            }

            // Création de la vente et des transactions caisse dans une transaction
            $sale = DB::transaction(function () use ($saleData, $user, $request, $validated) {
                $sale = $this->saleService->createSale($saleData, $user);

                // Normalisation des paiements
                $payments = $request->has('payments')
                    ? $validated['payments']
                    : [['payment_id' => $validated['payment_id'], 'amount' => $validated['amount_received'], 'reference' => null]];

                foreach ($payments as $payment) {
                    $paymentMethod = Payment::find($payment['payment_id']);
                    if ($paymentMethod && strtolower($paymentMethod->name) === 'espèce') {
                        $this->cashTransactionService->createTransaction([
                            'session_id' => $validated['cash_register_session_id'],
                            'sale_id' => $sale->id, // Add this line
                            'type' => 'sale',
                            'amount' => $payment['amount'],
                            'description' => "Vente n°{$sale->id} - Paiement espèces",
                            'reference' => $payment['reference'] ?? 'VENTE_' . $sale->id,
                            'created_by' => $user->id,
                        ]);
                    }
                }

                return $sale;
            });

            $formattedData = $this->saleService->getFormattedSaleData($sale);

            return response()->json([
                'success' => true,
                'message' => 'Vente créée avec succès',
                'data' => $formattedData
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/pending-orders
     *
     * Crée une commande en attente (non payée, pour commande en salle)
     *
     * @param Request $request Requête HTTP contenant :
     *                         REQUIS :
     *                         - table_id (int) : ID de la table (existe dans tables)
     *                         - user_id (int) : ID du caissier (existe dans users)
     *                         - point_of_sale_id (int) : ID du point de vente (existe dans point_of_sales)
     *                         - cash_register_session_id (int) : ID session caisse (existe dans cash_register_sessions)
     *                         - order_lines (array) : Lignes de commande (min:1)
     *                           - order_lines.*.product_id (int) : ID produit (existe dans products)
     *                           - order_lines.*.quantity (int) : Quantité (min:1)
     *                           - order_lines.*.price (float) : Prix unitaire (min:0)
     *                         OPTIONNELS :
     *                         - discount_percentage (float) : Remise en % (min:0, max:100)
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (201) : Instance de Sale (commande en attente) avec relations (orderLines.product, table)
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée
     *         - 422 : Erreur de validation
     *         - 500 : Erreur serveur
     */
    public function createPendingOrder(Request $request)
    {
        $user = auth()->guard('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
        }

        if (!$user->hasPermissionTo('create.sales', 'api')) {
            return response()->json(['message' => 'Vous n\'avez pas la permission de créer une commande.'], 403);
        }

        try {
            $validated = $request->validate([
                'table_id' => 'required|exists:tables,id',
                'user_id' => 'required|exists:users,id',
                'point_of_sale_id' => 'required|exists:point_of_sales,id',
                'cash_register_session_id' => 'required|exists:cash_register_sessions,id',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'order_lines' => 'required|array|min:1',
                'order_lines.*.product_id' => 'required|exists:products,id',
                'order_lines.*.quantity' => 'required|integer|min:1',
                'order_lines.*.price' => 'required|numeric|min:0',
            ]);

            $sale = $this->saleService->createPendingOrder($validated, $user);
            return response()->json($sale, 201);

        } catch (ValidationException $e) {
            return response()->json(['message' => 'Erreur de validation', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/sales/{id}
     *
     * Récupère les détails d'une vente spécifique
     *
     * @param int|string $id ID de la vente
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Instance de Sale avec relations (orderLines.product, payments.payment)
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée
     *         - 404 : Vente non trouvée
     */
    public function show($id)
    {
        try {
            if ($id === 'current-session') {
                return response()->json(['error' => 'Invalid sale ID.'], 400);
            }

            $sale = Sale::with(['orderLines.product', 'payments.payment'])->findOrFail($id);
            $user = auth()->guard('api')->user();

            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            if (!$user->hasPermissionTo('view.sales', 'api')) {
                return response()->json(['message' => 'Vous n\'avez pas la permission de voir cette vente.'], 403);
            }

            $isAdmin = $user->hasRole('admin', 'api');
            $isManager = $user->hasAnyRole(['gerant', 'gérant'], 'api');

            if (!$isAdmin) {
                if ($isManager) {
                    $assignedPosIds = $user->pointsOfSale()->pluck('point_of_sales.id')->toArray();
                    if (empty($assignedPosIds) && $user->point_of_sale_id) $assignedPosIds = [$user->point_of_sale_id];

                    if (!empty($assignedPosIds) && !in_array((int)$sale->point_of_sale_id, $assignedPosIds)) {
                        return response()->json(['message' => 'Cette vente n\'appartient pas à vos points de vente.'], 403);
                    }
                    if (empty($assignedPosIds) && (int) $sale->user_id !== (int) $user->id) {
                        return response()->json(['message' => 'Action non autorisée.'], 403);
                    }
                } else {
                    if ((int) $sale->user_id !== (int) $user->id) {
                        return response()->json(['message' => 'Action non autorisée.'], 403);
                    }
                }
            }

            return response()->json($sale);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée.'], 404);
        }
    }



    /**
     * DELETE /api/sales/{id}
     *
     * Supprime une vente (soft delete)
     * (Seuls les administrateurs peuvent supprimer)
     *
     * @param int|string $id ID de la vente à supprimer
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (204) : Pas de contenu
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée
     *         - 404 : Vente non trouvée
     */
    public function destroy($id)
    {
        try {
            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            if (!$user->hasPermissionTo('delete.sales', 'api') && !$user->hasRole('admin', 'api')) {
                return response()->json(['message' => 'Vous n\'avez pas la permission de supprimer une vente.'], 403);
            }

            if (!$user->hasRole('admin', 'api') && $this->userIsManager($user)) {
                return response()->json(['message' => 'Les gérants ne peuvent pas supprimer une vente.'], 403);
            }

            $sale = Sale::findOrFail($id);
            $sale->delete();

            return response()->json(['message' => 'Vente supprimée avec succès'], 204);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée.'], 404);
        }
    }

    /**
     * GET /api/sales/current-session
     *
     * Récupère toutes les ventes de la session de caisse actuellement ouverte pour l'utilisateur
     *
     * @param Request $request Requête HTTP
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : {
     *             success: true,
     *             session_info: {
     *                 id: int,
     *                 opened_at: string,
     *                 starting_amount: float,
     *                 current_total_sales: float
     *             },
     *             sales_count: int,
     *             data: array  // Collection des ventes
     *         }
     *         ERREURS :
     *         - 404 : Aucune session ouverte trouvée
     *         - 500 : Erreur serveur
     */
    public function getSalesForCurrentSession(Request $request)
    {
        try {
            $user = $request->user();

            $currentSession = CashRegisterSession::where('user_id', $user->id)
                ->where('is_closed', false)
                ->latest('opened_at')
                ->first();

            if (!$currentSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune session de caisse ouverte trouvée pour cet utilisateur.'
                ], 404);
            }

            $sales = Sale::with(['orderLines.product', 'payments.payment'])
                ->where('cash_register_session_id', $currentSession->id)
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'success' => true,
                'session_info' => [
                    'id' => $currentSession->id,
                    'opened_at' => $currentSession->opened_at,
                    'starting_amount' => $currentSession->starting_amount,
                    'current_total_sales' => $currentSession->total_sales,
                ],
                'sales_count' => $sales->count(),
                'data' => $sales
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la récupération des ventes.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/sales/{saleId}/add-products
     *
     * Ajoute des produits à une commande en attente
     *
     * @param Request $request Requête HTTP contenant :
     *                         REQUIS :
     *                         - order_lines (array) (min:1)
     *                           - order_lines.*.product_id (int) : ID produit (existe dans products)
     *                           - order_lines.*.quantity (int) : Quantité (min:1)
     *                           - order_lines.*.price (float) : Prix unitaire (min:0)
     * @param int|string $saleId ID de la commande
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Instance de Sale mise à jour
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 404 : Commande non trouvée
     *         - 422 : Erreur de validation
     *         - 500 : Erreur serveur
     */
    public function addToPendingOrder(Request $request, $saleId)
    {
        try {
            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            $validated = $request->validate([
                'order_lines' => 'required|array|min:1',
                'order_lines.*.product_id' => 'required|exists:products,id',
                'order_lines.*.quantity' => 'required|integer|min:1',
                'order_lines.*.price' => 'required|numeric|min:0',
            ]);

            $sale = Sale::findOrFail($saleId);
            $updatedSale = $this->saleService->addToPendingOrder($sale, $validated['order_lines']);

            return response()->json($updatedSale, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Commande non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/{saleId}/remove-products
     *
     * Supprime des lignes de commande d'une commande en attente
     *
     * @param Request $request Requête HTTP contenant :
     *                         REQUIS :
     *                         - order_line_ids (array) (min:1)
     *                           - order_line_ids.* (int) : IDs des lignes à supprimer (existent dans order_lines)
     * @param int|string $saleId ID de la commande
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Instance de Sale mise à jour
     *         ERREURS :
     *         - 404 : Commande non trouvée
     *         - 422 : Erreur de validation
     *         - 500 : Erreur serveur
     */
    public function removeFromPendingOrder(Request $request, $saleId)
    {
        try {
            $validated = $request->validate([
                'order_line_ids' => 'required|array|min:1',
                'order_line_ids.*' => 'required|exists:order_lines,id',
            ]);

            $sale = Sale::findOrFail($saleId);
            $updatedSale = $this->saleService->removeFromPendingOrder($sale, $validated['order_line_ids']);

            return response()->json($updatedSale, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Commande non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sales/{saleId}/cancel
     *
     * Annule une vente (rembourse si déjà payée)
     *
     * @param Request $request Requête HTTP contenant :
     *                         OPTIONNELS :
     *                         - reason (string|null) : Motif de l'annulation (max:255)
     * @param int|string $saleId ID de la vente à annuler
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Instance de Sale annulée
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 404 : Vente non trouvée
     *         - 422 : Erreur de validation
     *         - 500 : Erreur serveur
     */
    public function cancelSale(Request $request, $saleId)
    {
        try {
            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);

            $sale = Sale::findOrFail($saleId);
            $cancelledSale = $this->saleService->cancelSale($sale, $validated['reason'] ?? null);

            return response()->json($cancelledSale, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Erreur de validation', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/sales/{saleId}/formatted
     *
     * Récupère les données d'une vente formatées par catégorie
     * (Version complète avec toutes les informations)
     *
     * @param int|string $saleId ID de la vente
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : {
     *             success: true,
     *             data: array  // Données formatées (sale, categories, totals, payments)
     *         }
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée
     *         - 404 : Vente non trouvée
     *         - 500 : Erreur serveur
     */
    public function getFormattedSale($saleId)
    {
        try {
            $sale = Sale::with([
                'orderLines.product.category',
                'user',
                'table',
                'pointOfSale',
                'payments.payment'
            ])->findOrFail($saleId);

            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            if (!$user->hasPermissionTo('view.sales', 'api')) {
                return response()->json(['message' => 'Vous n\'avez pas la permission de voir cette vente.'], 403);
            }

            $formattedData = $this->saleService->getFormattedSaleData($sale);

            return response()->json([
                'success' => true,
                'data' => $formattedData
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/sales/{saleId}/categories
     *
     * Récupère uniquement les articles d'une vente regroupés par catégorie
     * (Version simplifiée sans les détails de paiement)
     *
     * @param int|string $saleId ID de la vente
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : {
     *             success: true,
     *             sale_id: int,
     *             ticket_number: int,
     *             table: string,
     *             cashier: string,
     *             date: string,
     *             categories: array,
     *             total_amount: float
     *         }
     *         ERREURS :
     *         - 401 : Utilisateur non authentifié
     *         - 403 : Permission refusée
     *         - 404 : Vente non trouvée
     *         - 500 : Erreur serveur
     */
    public function getSaleCategories($saleId)
    {
        try {
            $sale = Sale::with([
                'orderLines.product.category',
                'table',
                'user'
            ])->findOrFail($saleId);

            $user = auth()->guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié.'], 401);
            }

            if (!$user->hasPermissionTo('view.sales', 'api')) {
                return response()->json(['message' => 'Accès non autorisé.'], 403);
            }

            $categories = $this->saleService->getItemsGroupedByCategory($sale);

            return response()->json([
                'success' => true,
                'sale_id' => $sale->id,
                'ticket_number' => $sale->ticket_number,
                'table' => $sale->table?->table_number ?? 'Emporter',
                'cashier' => $sale->user?->name ?? 'Inconnu',
                'date' => $sale->created_at->format('d/m/Y H:i'),
                'categories' => $categories,
                'total_amount' => $sale->final_amount
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/sales/tables/{tableId}/pending-orders
     *
     * Récupère toutes les commandes en attente (pending) pour une table spécifique
     *
     * @param int|string $tableId ID de la table
     * @return \Illuminate\Http\JsonResponse
     *         SUCCÈS (200) : Collection des commandes en attente
     *         ERREURS :
     *         - 500 : Erreur serveur
     */
    public function getPendingOrdersForTable($tableId)
    {
        try {
            $orders = Sale::with(['order_lines.product', 'user'])
                ->where('table_id', $tableId)
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($orders);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la récupération des commandes.'], 500);
        }
    }

    // ========== MÉTHODES PRIVÉES ==========

    /**
     * Vérifie si un utilisateur a le rôle de gérant (gerant ou gérant)
     *
     * @param User|null $user Instance utilisateur ou null
     * @return bool True si l'utilisateur est gérant, false sinon
     */
    private function userIsManager(?User $user): bool
    {
        if (!$user)
            return false;
        return $user->hasAnyRole(['gerant', 'gérant'], 'api');
    }
       /**
     * PUT /api/sales/{sale}/order-lines
     * Remplace complètement les lignes de commande (utilisé par EditSaleModal)
     */
    public function replaceOrderLines(Request $request, Sale $sale)
    {
        try {
            $request->validate([
                'orderlines' => 'required|array',
                'orderlines.*.product_id' => 'required|exists:products,id',
                'orderlines.*.quantity'   => 'required|integer|min:1',
                'orderlines.*.price'      => 'required|numeric|min:0',
            ]);

            // Optionnel : Restreindre la modification aux ventes "pending" ou aux admins
            if ($sale->status === 'completed' && !auth()->user()->hasRole('admin')) {
                return response()->json(['error' => 'Les ventes terminées ne peuvent pas être modifiées.'], 422);
            }

            DB::beginTransaction();

            // Supprimer les anciennes lignes
            $sale->orderlines()->delete();

            // Ajouter les nouvelles lignes
            foreach ($request->orderlines as $line) {
                $sale->orderlines()->create([
                    'product_id' => $line['product_id'],
                    'quantity'   => $line['quantity'],
                    'price'      => $line['price'],
                    'total'      => $line['quantity'] * $line['price'],
                ]);
            }

            // Recalculer les totaux
            $totalAmount = $sale->orderlines()->sum('total');
            $sale->update([
                'total_amount' => $totalAmount,
                'final_amount' => $totalAmount * (1 - ($sale->discount_percentage ?? 0) / 100),
            ]);

            DB::commit();

            $sale->load(['orderlines.product', 'payments']);

            return response()->json([
                'success' => true,
                'message' => 'Lignes de commande mises à jour avec succès',
                'sale' => $sale
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('replaceOrderLines Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Erreur lors de la mise à jour des lignes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/sales/{id}
     * Mise à jour générale d'une vente
     */
    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            if (!$user->hasRole('admin')) {
                return response()->json(['message' => 'Seuls les administrateurs peuvent modifier une vente.'], 403);
            }

            $sale = Sale::findOrFail($id);

            $validated = $request->validate([
                'discount_percentage' => 'nullable|numeric|min:0|max:100',
                'status' => 'sometimes|in:pending,completed,cancelled',
                'notes' => 'nullable|string',
                'orderlines' => 'sometimes|array',
                'orderlines.*.product_id' => 'required|exists:products,id',
                'orderlines.*.quantity' => 'required|integer|min:1',
                'orderlines.*.price' => 'required|numeric|min:0',
            ]);

            DB::beginTransaction();

            // Mise à jour des champs simples
            $sale->update(Arr::except($validated, ['orderlines']));

            // Mise à jour des orderlines si envoyées
            if (isset($validated['orderlines'])) {
                $sale->orderlines()->delete();

                foreach ($validated['orderlines'] as $line) {
                    $sale->orderlines()->create([
                        'product_id' => $line['product_id'],
                        'quantity'   => $line['quantity'],
                        'price'      => $line['price'],
                        'total'      => $line['quantity'] * $line['price'],
                    ]);
                }
            }

            $sale->refresh();
            $sale->updateTotalAmount();

            DB::commit();

            $sale->load('orderlines.product');

            return response()->json([
                'success' => true,
                'message' => 'Vente mise à jour avec succès',
                'data' => $sale
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Vente non trouvée'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
