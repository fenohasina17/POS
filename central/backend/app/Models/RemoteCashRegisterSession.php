<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteCashRegisterSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'restaurant_id', 'remote_id', 'starting_amount',
        'actual_cash_amount', 'expected_cash_amount', 'total_sales',
        'total_refunds', 'is_closed', 'has_discrepancy',
        'user_id_remote', 'remote_opened_at', 'remote_closed_at', 'received_at',
    ];

    protected $casts = [
        'is_closed'        => 'boolean',
        'has_discrepancy'  => 'boolean',
        'remote_opened_at' => 'datetime',
        'remote_closed_at' => 'datetime',
        'received_at'      => 'datetime',
    ];
}
