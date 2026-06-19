<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Traits\ScopeByPos;

    protected $table = 'sales';

    protected $fillable = [
        'sale_number',
        'ticket_number',
        'user_id',
        'point_of_sale_id',
        'cash_register_session_id',
        'table_id',
        'total_amount',
        'discount_percentage',
        'final_amount',
        'amount_received',
        'change_amount',
        'status',
        'notes',
        'cancelled_at',
        'cancellation_reason',
        'deleted_by',
        'deletion_reason',
    ];

    protected $casts = [
        'total_amount'       => 'decimal:2',
        'discount_percentage'=> 'decimal:2',
        'final_amount'       => 'decimal:2',
        'amount_received'    => 'decimal:2',
        'change_amount'      => 'decimal:2',
        'cancelled_at'       => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // ======================== EVENTS ========================

    protected static function booted()
    {
        static::creating(function ($sale) {
            if (empty($sale->sale_number)) {
                $pos = PointOfSale::find($sale->point_of_sale_id);
                $prefix = strtoupper($pos?->code ?? $pos?->name ?? 'POS');

                $lastSale = static::where('point_of_sale_id', $sale->point_of_sale_id)
                    ->orderBy('id', 'desc')
                    ->first();

                $nextNumber = $lastSale
                    ? (int) substr($lastSale->sale_number, -6) + 1
                    : 1;

                $sale->sale_number = $prefix . '_V-' . now()->format('Ymd') . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    // ======================== RELATIONS ========================

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

    /**
     * Relation principale (nom correct dans la base de données)
     */
    public function orderlines()
    {
        return $this->hasMany(OrderLine::class);   // Assure-toi que le modèle s'appelle OrderLine
    }

    /**
     * Alias pour compatibilité avec le frontend (order_lines)
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

    // ======================== MÉTHODES UTILES ========================

    /**
     * Recalcule et met à jour les montants totaux
     */
    public function updateTotalAmount(): void
    {
        $total = $this->orderlines()->sum('total');

        $this->total_amount = $total;
        $this->final_amount = $total * (1 - ($this->discount_percentage ?? 0) / 100);
        $this->save();
    }

    /**
     * Accesseur pour le nom du caissier
     */
    public function getCashierNameAttribute()
    {
        return $this->user?->name ?? 'Inconnu';
    }

    /**
     * Accesseur pour le statut lisible
     */
    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'completed' => 'Terminée',
            'pending'   => 'En attente',
            'cancelled' => 'Annulée',
            default     => ucfirst($this->status),
        };
    }

    /**
     * Vérifie si la vente peut être modifiée
     */
    public function canBeModified(): bool
    {
        return $this->status === 'pending' || auth()->user()?->hasRole('admin');
    }
}
