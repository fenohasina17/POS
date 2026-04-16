<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionClosure extends Model
{
    protected $fillable = [
        'session_id',
        'closed_by_user_id',
        'notes',
    ];

    // Relations
    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}

