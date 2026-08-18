<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory, \App\Models\Traits\ScopeByPos;

    protected $fillable = [
        'name',
        'point_of_sale_id',
    ];

    protected $casts = [];

    protected $appends = [
        'is_occupied',
    ];

    public function pointOfSale()
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function currentSession()
    {
        return $this->hasOne(CashRegisterSession::class)
            ->where('is_closed', false)
            ->latestOfMany('opened_at');
    }

    public function sessions()
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function printers()
    {
        return $this->hasMany(Printer::class);
    }

    public function getIsOccupiedAttribute(): bool
    {

        $session = $this->currentSession;
        if ($session !== null) {
            return !$session->is_closed;
        }

        return $this->currentSession()->where('is_closed', false)->exists();
    }
}
