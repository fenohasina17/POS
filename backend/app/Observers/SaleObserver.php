<?php

namespace App\Observers;

use App\Models\Sale;
use App\Models\Table;

class SaleObserver
{
    public function deleted(Sale $sale)
    {
        
        if ($sale->table_id) {
            Table::where('id', $sale->table_id)->update(['status' => 'available']);
        }
    }
}