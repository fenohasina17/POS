<?php
// app/Models/Sale.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'sale_number',
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
        'deleted_by',
        'deletion_reason'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];
    protected static function booted()
    {
        static::creating(function ($sale) {
            $pos = \App\Models\PointOfSale::find($sale->point_of_sale_id);
            $prefix = strtoupper($pos->code ?? $pos->name); // ex: "CENTRE"

            $lastSale = static::where('point_of_sale_id', $sale->point_of_sale_id)
                ->orderBy('id', 'desc')
                ->first();
            $nextNumber = $lastSale ? (intval(substr($lastSale->sale_number, -6)) + 1) : 1;

            $sale->sale_number = $prefix . '_V-' . date('Ymd') . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }

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

    /**
     * Alias pour orderlines (utilisé par le frontend)
     */
    public function order_lines()
    {
        return $this->orderlines();
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
