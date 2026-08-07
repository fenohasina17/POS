<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteSale extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'restaurant_id', 'remote_id', 'sale_number',
        'ticket_number', 'status', 'total_amount', 'final_amount',
        'discount_percentage', 'amount_received', 'change_amount',
        'user_id_remote', 'point_of_sale_id_remote', 'session_id_remote',
        'table_id_remote', 'remote_created_at', 'remote_completed_at', 'received_at',
    ];

    protected $casts = [
        'remote_created_at'   => 'datetime',
        'remote_completed_at' => 'datetime',
        'received_at'         => 'datetime',
    ];
}
