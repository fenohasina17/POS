<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'restaurant_id', 'remote_id', 'sale_id_remote',
        'product_id_remote', 'product_name', 'category_name', 'quantity',
        'unit_price', 'total', 'remote_created_at', 'received_at',
    ];

    protected $casts = [
        'remote_created_at' => 'datetime',
        'received_at'       => 'datetime',
    ];
}
