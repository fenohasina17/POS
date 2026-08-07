<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteSalePayment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'restaurant_id', 'remote_id', 'sale_id_remote',
        'payment_method_name', 'amount', 'remote_created_at', 'received_at',
    ];

    protected $casts = [
        'remote_created_at' => 'datetime',
        'received_at'       => 'datetime',
    ];
}
