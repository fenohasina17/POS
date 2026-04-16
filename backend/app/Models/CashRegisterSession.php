<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CashRegisterSession extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        // Identifiants
        'cash_register_id',
        'user_id',
        'closed_by_user_id',

        // Montants financiers
        'starting_amount',
        'expected_cash_amount',
        'actual_cash_amount',
        'difference_amount',
        'total_sales',
        'total_refunds',

        // États et compteurs
        'is_closed',
        'is_bill_checked',
        'has_discrepancy',
        'start_ticket_number',

        // Notes et explications
        'closing_notes',
        'discrepancy_explanation',
        'notes',

        // Dates
        'opened_at',
        'closed_at',
    ];

    /**
     * Casts pour garantir les types de données.
     */
    protected $casts = [
        'is_closed' => 'boolean',
        'is_bill_checked' => 'boolean',
        'has_discrepancy' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'starting_amount' => 'decimal:2',
        'expected_cash_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'difference_amount' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'total_refunds' => 'decimal:2',
    ];

    // --- Relations ---

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation avec l'utilisateur qui a fermé la session.
     */
    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Relation avec les transactions cash
     * Une session a plusieurs transactions cash
     */
    public function cashTransactions()
    {
        return $this->hasMany(CashTransaction::class, 'session_id');
    }

    /**
     * Alias pour garder la compatibilité avec l'ancien nom
     */
    public function transactions()
    {
        return $this->cashTransactions();
    }

    public function discrepancies()
    {
        return $this->hasMany(SessionDiscrepancy::class, 'session_id');
    }

    public function closures()
    {
        return $this->hasMany(SessionClosure::class, 'session_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'cash_register_session_id');
    }

    // --- Méthodes de calcul ---

    /**
     * Calcule le montant théorique en caisse
     * starting_amount + somme des entrées (ventes + dépôts) - somme des sorties (retraits)
     */
    public function getTheoreticalAmountAttribute(): float
    {
        $in = $this->cashTransactions()
            ->whereIn('type', ['sale', 'in'])
            ->sum('amount');
            
        $out = $this->cashTransactions()
            ->where('type', 'out')
            ->sum('amount');
        
        return (float) ($this->starting_amount + $in - $out);
    }

    /**
     * Calcule le total des ventes en espèces de la session
     */
    public function getTotalCashSalesAttribute(): float
    {
        return (float) $this->cashTransactions()
            ->where('type', 'sale')
            ->sum('amount');
    }

    /**
     * Calcule le total des remboursements en espèces de la session
     */
    public function getTotalCashRefundsAttribute(): float
    {
        return (float) $this->cashTransactions()
            ->where('type', 'refund')
            ->sum('amount');
    }

    /**
     * Calcule le total des dépôts (entrées) de la session
     */
    public function getTotalDepositsAttribute(): float
    {
        return (float) $this->cashTransactions()
            ->where('type', 'in')
            ->sum('amount');
    }

    /**
     * Calcule le total des retraits (sorties) de la session
     */
    public function getTotalWithdrawalsAttribute(): float
    {
        return (float) $this->cashTransactions()
            ->where('type', 'out')
            ->sum('amount');
    }

    /**
     * Calcule la différence entre le montant attendu et le montant théorique
     */
    public function getCalculatedDifferenceAttribute(): float
    {
        return (float) ($this->expected_cash_amount - $this->theoretical_amount);
    }

    /**
     * Vérifie s'il y a une différence significative
     */
    public function hasSignificantDifference(float $tolerance = 0.01): bool
    {
        return abs($this->calculated_difference) > $tolerance;
    }

    /**
     * Met à jour les totaux de la session à partir des transactions
     */
    public function refreshTotals(): void
    {
        $this->updateQuietly([
            'total_sales' => $this->total_cash_sales,
            'total_refunds' => $this->total_cash_refunds,
            'difference_amount' => $this->calculated_difference,
            'has_discrepancy' => $this->hasSignificantDifference(),
        ]);
    }

    // --- Méthodes de vérification ---

    /**
     * Vérifie si la session est ouverte
     */
    public function isOpen(): bool
    {
        return !$this->is_closed;
    }

    /**
     * Vérifie si la session est fermée
     */
    public function isClosed(): bool
    {
        return (bool) $this->is_closed;
    }

    /**
     * Vérifie si la session peut être fermée
     */
    public function canBeClosed(): bool
    {
        return $this->isOpen() && $this->sales()->where('status', 'pending')->count() === 0;
    }

    /**
     * Récupère le prochain numéro de ticket
     */
    public function getNextTicketNumberAttribute(): int
    {
        $maxTicket = $this->sales()->max('ticket_number');
        return $maxTicket ? $maxTicket + 1 : ($this->start_ticket_number ?? 1);
    }

    // --- Scope ---

    /**
     * Scope pour les sessions ouvertes
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Scope pour les sessions fermées
     */
    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    /**
     * Scope pour les sessions avec un écart
     */
    public function scopeWithDiscrepancy($query)
    {
        return $query->where('has_discrepancy', true);
    }

    // --- Events ---

    protected static function booted()
    {
        static::creating(function ($session) {
            if (empty($session->opened_at)) {
                $session->opened_at = now();
            }
            if (empty($session->start_ticket_number)) {
                $session->start_ticket_number = 1;
            }
            if (empty($session->starting_amount)) {
                $session->starting_amount = 0;
            }
        });

        static::updating(function ($session) {
            if ($session->is_closed && empty($session->closed_at)) {
                $session->closed_at = now();
            }
        });
    }
}