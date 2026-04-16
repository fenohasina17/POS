<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionDiscrepancy extends Model
{
  protected $fillable = [
    'session_id',
    'difference_amount', // Indispensable
    'explanation',       // Indispensable
    'is_checked',
];

    // Relation
    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }
}
