<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CashTransactionService
{
// app/Services/CashTransactionService.php

public function createTransaction(array $data)
{
    $session = CashRegisterSession::findOrFail($data['session_id']);

    // Vérification stricte
    if ($session->is_closed === true) {
        throw new AccessDeniedHttpException("Action impossible : La session est clôturée.");
    }

    return DB::transaction(function () use ($data, $session) {
        $transaction = CashTransaction::create($data);
        $this->applyEffect($session, $transaction->type, $transaction->amount);
        return $transaction;
    });
}

public function updateTransaction(CashTransaction $transaction, array $data)
{
    $transaction->loadMissing('session');
    $session = $transaction->session;

    // Vérification stricte
    if (!$session || $session->is_closed === true) {
        throw new AccessDeniedHttpException("Modification impossible : La session est clôturée.");
    }

    return DB::transaction(function () use ($transaction, $data, $session) {
        $this->applyEffect($session, $transaction->type, $transaction->amount, true);
        $transaction->update($data);
        $this->applyEffect($session, $transaction->type, $transaction->amount);
        return $transaction;
    });
}
public function deleteTransaction(CashTransaction $transaction)
{
    \Log::info('=== SERVICE DELETE DEBUG ===');
    \Log::info('Transaction ID: ' . $transaction->id);
    \Log::info('Transaction session_id: ' . $transaction->session_id);
    
    // Méthode 1: loadMissing
    $transaction->loadMissing('session');
    $session = $transaction->session;
    
    \Log::info('After loadMissing - Session: ' . ($session ? $session->id : 'null'));
    
    // Méthode 2: find direct
    $sessionDirect = CashRegisterSession::find($transaction->session_id);
    \Log::info('Direct find - Session: ' . ($sessionDirect ? $sessionDirect->id : 'null'));
    
    if (!$sessionDirect || $sessionDirect->isClosed()) {
        throw new AccessDeniedHttpException("Suppression impossible : La session est clôturée.");
    }

    DB::transaction(function () use ($transaction, $sessionDirect) {
        $this->applyEffect($sessionDirect, $transaction->type, $transaction->amount, true);
        $transaction->delete();
    });
}
    private function applyEffect(CashRegisterSession $session, string $type, $amount, bool $reverse = false)
    {
        $delta = ($type === 'sale') ? $amount : -$amount;
        if ($reverse) {
            $delta = -$delta;
        }

        $session->increment('expected_cash_amount', $delta);
    }
}