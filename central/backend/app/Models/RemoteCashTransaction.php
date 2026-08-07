<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RemoteCashTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'restaurant_id', 'remote_id', 'type',
        'amount', 'label', 'session_id_remote', 'remote_created_at', 'received_at',
    ];

    protected $casts = [
        'remote_created_at' => 'datetime',
        'received_at'       => 'datetime',
    ];
}
