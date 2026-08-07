<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\CashRegisterSession;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CashTransactionService
{


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
    $session = CashRegisterSession::find($transaction->session_id);

    if (!$session || $session->isClosed()) {
        throw new AccessDeniedHttpException("Suppression impossible : La session est clôturée.");
    }

    DB::transaction(function () use ($transaction, $session) {
        $this->applyEffect($session, $transaction->type, $transaction->amount, true);
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