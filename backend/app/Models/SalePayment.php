<?php
// app/Models/SalePayment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    use HasFactory;

    protected $table = 'sale_payments';

    protected $fillable = [
        'sale_id',
        'payment_id',
        'amount',
        'reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // ========== RELATIONS ==========

    /**
     * Relation avec la vente
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Relation avec le mode de paiement
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}