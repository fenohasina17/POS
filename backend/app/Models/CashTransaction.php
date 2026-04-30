<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashTransaction extends Model
{
    protected $fillable = [
        'session_id',
        'sale_id',
        'type',
        'amount',
        'description',
        'created_by', 
        
    ];

    /**
     * Relation : Une transaction appartient à une session de caisse
     */
    public function session()
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }

    /**
     * Relation : Une transaction peut être liée à une vente (si type='sale' ou 'refund')
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Vérifie si c'est une transaction de vente
     */
    public function isSale(): bool
    {
        return $this->type === 'sale';
    }

    /**
     * Vérifie si c'est un remboursement
     */
    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    /**
     * Vérifie si c'est un dépôt
     */
    public function isIn(): bool
    {
        return $this->type === 'in';
    }

    /**
     * Vérifie si c'est un retrait
     */
    public function isOut(): bool
    {
        return $this->type === 'out';
    }
}