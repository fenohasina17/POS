<?php

namespace App\Observers;

use App\Models\FinancialAuditLog;
use App\Models\Sale;
use App\Models\Table;

class SaleObserver
{
    private array $trackedFields = [
        'status', 'total_amount', 'discount_percentage', 'final_amount',
        'amount_received', 'change_amount', 'cancelled_at', 'cancellation_reason',
        'deleted_by', 'deletion_reason',
    ];

    public function created(Sale $sale): void
    {
        FinancialAuditLog::record($sale, 'created', null, $sale->only($this->trackedFields));
    }

    public function updated(Sale $sale): void
    {
        $changed = array_intersect_key($sale->getChanges(), array_flip($this->trackedFields));
        if (empty($changed)) {
            return;
        }

        FinancialAuditLog::record(
            $sale,
            'updated',
            array_intersect_key($sale->getOriginal(), array_flip($this->trackedFields)),
            $changed
        );
    }

    public function deleted(Sale $sale): void
    {
        if ($sale->table_id) {
            Table::where('id', $sale->table_id)->update(['status' => 'available']);
        }

        FinancialAuditLog::record($sale, 'deleted', $sale->only($this->trackedFields), null);
    }
}
