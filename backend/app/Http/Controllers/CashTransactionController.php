<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Services\CashTransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CashTransactionController extends Controller
{
    public function getBySession($sessionId): JsonResponse
{
    try {
        $transactions = CashTransaction::where('session_id', $sessionId)->get();

        $in = $transactions->filter(function ($t) {
            return $t->type === 'sale' || ($t->type === 'adjustment' && $t->amount > 0);
        })->values();

        $out = $transactions->filter(function ($t) {
            return $t->type === 'refund' || ($t->type === 'adjustment' && $t->amount < 0);
        })->values();

        return response()->json(compact('in', 'out'));
    } catch (\Exception $e) {
        \Log::error('Erreur getBySession: ' . $e->getMessage());
        return response()->json(['error' => 'Erreur interne'], 500);
    }
}
    // Injection du service via le constructeur
    public function __construct(
        protected CashTransactionService $transactionService
    ) {}

    /**
     * Liste toutes les transactions.
     */
    public function index(): JsonResponse
    {
        return response()->json(CashTransaction::all());
    }

    /**
     * Création d'une transaction.
     */
   public function store(Request $request)
{
    // C'est cette ligne qui fait échouer l'utilisateur non autorisé
    $this->authorize('create', CashTransaction::class);

    $validated = $request->validate([
        'session_id' => 'required|exists:cash_register_sessions,id',
        'type' => 'required|in:sale,refund',
        'amount' => 'required|numeric|min:0',
        'description' => 'nullable|string'
    ]);

    $transaction = $this->transactionService->createTransaction($validated);
    return response()->json($transaction, 201);
}


public function destroy(CashTransaction $cashTransaction)
{
    try {
        // Debug
        \Log::info('=== DESTROY DEBUG ===');
        \Log::info('Transaction ID: ' . $cashTransaction->id);
        \Log::info('Session ID: ' . $cashTransaction->session_id);
        
        // Vérifier l'autorisation
        $this->authorize('delete', $cashTransaction);
        \Log::info('Authorization passed');
        
        // Appeler le service
        $this->transactionService->deleteTransaction($cashTransaction);
        \Log::info('Transaction deleted successfully');
        
        return response()->noContent();
        dd($this->transactionService->deleteTransaction($cashTransaction) );
        
    } catch (\Exception $e) {
        \Log::error('Delete error: ' . $e->getMessage());
        throw $e;
    }
}
    /**
     * Détails d'une transaction.
     */
    public function show($id): JsonResponse
    {
        $transaction = CashTransaction::findOrFail($id);
        return response()->json($transaction);
    }

    /**
     * Mise à jour d'une transaction.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $transaction = CashTransaction::findOrFail($id);

        $validated = $request->validate([
            'session_id'  => 'sometimes|exists:cash_register_sessions,id',
            'type'        => 'sometimes|in:sale,refund,adjustment',
            'amount'      => 'sometimes|integer',
            'description' => 'nullable|string',
        ]);

        $updatedTransaction = $this->transactionService->updateTransaction($transaction, $validated);

        return response()->json($updatedTransaction);
    }


}