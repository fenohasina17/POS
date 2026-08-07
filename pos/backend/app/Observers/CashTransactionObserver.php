<?php

namespace App\Observers;

use App\Models\CashTransaction;
use App\Models\FinancialAuditLog;

class CashTransactionObserver
{
    private array $trackedFields = [
        'type', 'amount', 'label', 'session_id',
    ];

    public function created(CashTransaction $transaction): void
    {
        FinancialAuditLog::record(
            $transaction,
            'created',
            null,
            $transaction->only($this->trackedFields)
        );
    }

    public function updated(CashTransaction $transaction): void
    {
        $changed = array_intersect_key($transaction->getChanges(), array_flip($this->trackedFields));
        if (empty($changed)) {
            return;
        }

        FinancialAuditLog::record(
            $transaction,
            'updated',
            array_intersect_key($transaction->getOriginal(), array_flip($this->trackedFields)),
            $changed
        );
    }

    public function deleted(CashTransaction $transaction): void
    {
        FinancialAuditLog::record(
            $transaction,
            'deleted',
            $transaction->only($this->trackedFields),
            null
        );
    }
}
