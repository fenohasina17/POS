<?php
// app/Models/Sale.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $fillable = [
        'user_id',
        'point_of_sale_id',
        'cash_register_session_id',
        'table_id',
        'total_amount',
        'discount_percentage',
        'final_amount',
        'status',
        'ticket_number',
        'amount_received',
        'change_amount',
        'notes',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pointOfSale()
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function cashRegisterSession()
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function orderlines()
    {
        return $this->hasMany(Orderline::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function cashTransaction()
    {
        return $this->hasOne(CashTransaction::class);
    }

    /**
     * Récupère le premier paiement (pour compatibilité avec l'ancien code)
     */
    public function getPaymentIdAttribute()
    {
        $payment = $this->payments()->first();
        return $payment ? $payment->payment_id : null;
    }
}