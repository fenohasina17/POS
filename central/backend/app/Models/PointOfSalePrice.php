<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointOfSalePrice extends Model
{
    protected $fillable = ['restaurant_id', 'terminal_id', 'product_ref', 'price', 'is_local_override', 'reported_at'];

    protected $casts = [
        'price'             => 'decimal:2',
        'is_local_override' => 'boolean',
        'reported_at'       => 'datetime',
    ];
}
