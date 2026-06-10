<?php
// app/Http/Controllers/SalePaymentController.php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\SalePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class SalePaymentController extends Controller
{
    protected SalePaymentService $service;

    public function __construct(SalePaymentService $service)
    {
        $this->service = $service;
    }

    /* Ajouter des paiements à une vente
     */
    public function store(Request $request, $saleId)
    {
        try {
            $user = Auth::user();

            if (!Auth::check()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // Vérification de permission
            if (!$user->hasPermissionTo('create.sale_payments', 'api')) {
                return response()->json([
                    'message' => 'Vous n\'avez pas la permission d\'ajouter des paiements.'
                ], 403);
            }

            $sale = Sale::findOrFail($saleId);

            $isAdmin = $user->isAdmin();
            $activePosId = $request->attributes->get('activePosId');

            // Non-admin specific checks
            if (!$isAdmin) {
                if (!$activePosId) {
                    return response()->json([
                        'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                    ], 403);
                }
                if (!$user->pointsOfSale->contains($activePosId)) {
                    return response()->json([
                        'message' => 'Accès refusé pour ce point de vente.'
                    ], 403);
                }
                if ((int)$sale->point_of_sale_id !== (int)$activePosId) {
                    return response()->json([
                        'message' => 'La vente n\'appartient pas à votre point de vente actif.'
                    ], 403);
                }
            }

            // Gérants ne peuvent pas ajouter de paiements
            if ($user->hasRole('gerant', 'api')) {
                return response()->json([
                    'message' => 'Les gérants ne peuvent pas ajouter de paiements.'
                ], 403);
            }

            if ($sale->status === 'completed') {
                return response()->json([
                    'message' => 'Impossible d\'ajouter un paiement à une vente déjà complétée.'
                ], 422);
            }

            // ✅ Validation avec discount_percentage
            $validated = $request->validate([
                'payments' => 'required|array|min:1',
                'payments.*.payment_id' => 'required|exists:payments,id',
                'payments.*.amount' => 'required|numeric|min:0.01',
                'payments.*.reference' => 'nullable|string|max:100',
                'payments.*.notes' => 'nullable|string|max:255',
                'payments.*.discount_percentage' => 'nullable|numeric|min:0|max:100', // ✅ AJOUTÉ
                'change_amount' => 'nullable|numeric|min:0',
            ]);

            // ✅ Vérifier si un discount est présent et le logger
            $hasDiscount = collect($validated['payments'])->contains(fn($p) => isset($p['discount_percentage']));
            if ($hasDiscount) {
                Log::info('Discount appliqué lors du paiement', [
                    'sale_id' => $saleId,
                    'payments' => $validated['payments']
                ]);
            }

            // Appel au service (qui a déjà été modifié pour gérer discount_percentage)
            $result = $this->service->addPayments(
                $sale,
                $validated['payments'],
                $validated['change_amount'] ?? null
            );

            // Charger les relations pour la réponse
            $sale->refresh()->load(['payments.payment', 'orderlines.product']);

            return response()->json([
                'message' => 'Paiement(s) enregistré(s)',
                'sale' => $sale,
                'total_paid_now' => $result['total_paid_now'],
                'total_paid' => $result['new_total_paid'],
                'remaining' => $result['remaining'],
                'change' => $result['change_amount'],
                'is_completed' => $result['is_completed'],
                'discount_applied' => $sale->discount_percentage, // ✅ Ajouté
                'final_amount' => $sale->final_amount, // ✅ Ajouté
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Vente non trouvée'
            ], 404);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erreur dans SalePaymentController@store', [
                'sale_id' => $saleId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Lister les paiements d'une vente
     */
    public function index($saleId, Request $request)
    {
        $user = Auth::user();

        // ✅ Vérification de permission
        if (!Auth::check() || !$user->hasPermissionTo('view.sale_payments', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $sale = Sale::with('payments.payment')->findOrFail($saleId);

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin specific checks
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if ((int)$sale->point_of_sale_id !== (int)$activePosId) {
                return response()->json([
                    'message' => 'La vente n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
            // Caissier can only see their own sales
            if ($user->hasRole('caissier', 'api') && $user->id !== $sale->user_id) {
                return response()->json([
                    'message' => 'Vous ne pouvez voir que vos propres paiements.'
                ], 403);
            }
        }

        $totalPaid = $sale->amount_received ?? 0;

        return response()->json([
            'sale_id' => $sale->id,
            'final_amount' => $sale->final_amount,
            'total_paid' => $totalPaid,
            'remaining' => $this->service->getRemainingAmount($sale),
            'payments' => $sale->payments,
        ]);
    }

    /**
     * Voir un paiement spécifique
     */
    public function show($saleId, $paymentId, Request $request)
    {
        $user = Auth::user();

        // ✅ Vérification de permission
        if (!Auth::check() || !$user->hasPermissionTo('view.sale_payments', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $salePayment = SalePayment::where('sale_id', $saleId)
            ->with('payment')
            ->where('id', $paymentId)
            ->firstOrFail();

        $sale = $salePayment->sale;

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin specific checks
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if ((int)$sale->point_of_sale_id !== (int)$activePosId) {
                return response()->json([
                    'message' => 'La vente n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
            // Caissier can only see their own sales
            if ($user->hasRole('caissier', 'api') && $user->id !== $sale->user_id) {
                return response()->json([
                    'message' => 'Vous ne pouvez voir que vos propres paiements.'
                ], 403);
            }
        }

        return response()->json($salePayment);
    }

    /**
     * Modifier un paiement
     */
    public function update(Request $request, $saleId, $paymentId)
    {
        $user = Auth::user();

        // ✅ Vérification de permission
        if (!Auth::check() || !$user->hasPermissionTo('update.sale_payments', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $salePayment = SalePayment::where('sale_id', $saleId)
            ->where('id', $paymentId)
            ->firstOrFail();

        $sale = $salePayment->sale; // Access sale through salePayment

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin specific checks
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if ((int)$sale->point_of_sale_id !== (int)$activePosId) {
                return response()->json([
                    'message' => 'La vente n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
            // Caissier can only modify their own sales
            if ($user->hasRole('caissier', 'api') && $user->id !== $sale->user_id) {
                return response()->json([
                    'message' => 'Vous ne pouvez modifier que vos propres paiements.'
                ], 403);
            }
        }


        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:255',
        ]);

        $salePayment->update($validated);

        // Recalculer le total payé et mettre à jour la vente
        $totalPaid = $sale->payments()->sum('amount');
        $sale->update([
            'amount_received' => $totalPaid,
            'status' => $totalPaid >= $sale->final_amount ? 'completed' : 'pending'
        ]);

        return response()->json([
            'message' => 'Paiement modifié avec succès',
            'payment' => $salePayment->fresh()->load('payment'),
            'total_paid' => $totalPaid,
            'remaining' => max(0, $sale->final_amount - $totalPaid)
        ]);
    }

    /**
     * Supprimer un paiement
     */
    public function destroy($saleId, $paymentId, Request $request)
    {
        $user = Auth::user();

        // ✅ Vérification de permission
        if (!Auth::check() || !$user->hasPermissionTo('delete.sale_payments', 'api')) {
            abort(403, 'This action is unauthorized.');
        }

        $salePayment = SalePayment::where('sale_id', $saleId)
            ->where('id', $paymentId)
            ->firstOrFail();

        $sale = $salePayment->sale; // Access sale through salePayment

        $isAdmin = $user->isAdmin();
        $activePosId = $request->attributes->get('activePosId');

        // Non-admin specific checks
        if (!$isAdmin) {
            if (!$activePosId) {
                return response()->json([
                    'message' => 'Point de vente actif non défini pour l\'utilisateur.'
                ], 403);
            }
            if (!$user->pointsOfSale->contains($activePosId)) {
                return response()->json([
                    'message' => 'Accès refusé pour ce point de vente.'
                ], 403);
            }
            if ((int)$sale->point_of_sale_id !== (int)$activePosId) {
                return response()->json([
                    'message' => 'La vente n\'appartient pas à votre point de vente actif.'
                ], 403);
            }
            // Caissier can only delete payments from their own sales
            if ($user->hasRole('caissier', 'api') && $user->id !== $sale->user_id) {
                return response()->json([
                    'message' => 'Vous ne pouvez supprimer que vos propres paiements.'
                ], 403);
            }
        }

        // Supprimer le paiement
        $salePayment->delete();

        // Mettre à jour le montant reçu de la vente
        $totalPaid = $sale->payments()->sum('amount');
        $sale->update([
            'amount_received' => $totalPaid,
            'status' => $totalPaid >= $sale->final_amount ? 'completed' : 'pending'
        ]);

        return response()->json([
            'message' => 'Paiement supprimé avec succès',
            'total_paid' => $totalPaid,
            'remaining' => max(0, $sale->final_amount - $totalPaid),
            'sale_status' => $sale->status
        ]);
    }
}