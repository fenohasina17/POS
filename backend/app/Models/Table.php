<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Table extends Model
{
    use HasFactory, \App\Models\Traits\ScopeByPos;

    // Définition des zones pour la liste déroulante
    public static $availableZones = [
        'rdc'       => 'Rez-de-chaussée',
        'etage_1'   => 'Étage 1',
        'etage_2'   => 'Étage 2',
        'terrasse'  => 'Terrasse',
        'exterieur' => 'Extérieur',
        'jardin'    => 'Jardin',
        'vip'       => 'Espace VIP',
        'bar'       => 'Bar / Comptoir'
    ];

    protected $fillable = [
        'table_number',
        'name',
        'capacity',
        'status',
        'description',
        'point_of_sale_id',
        'location',
        'locked_by_session_id',
        'locked_at'
    ];

    protected $casts = [
        'location' => 'array',
    ];

    // --- RELATIONS ---

    public function lockedBySession()
    {
        return $this->belongsTo(CashRegisterSession::class, 'locked_by_session_id');
    }

    public function pointOfSale()
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // --- ACCESSORS & MUTATORS ---

    public function getStatusAttribute()
    {
        if ($this->locked_by_session_id) {
            return 'occupied';
        }
        return $this->attributes['status'] ?? 'available';
    }

    // --- SCOPES ---

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }

    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    public function scopeOutOfOrder($query)
    {
        return $query->where('status', 'out_of_order');
    }

    public function scopeInZone($query, $zone)
    {
        // Recherche dans le champ JSON location
        return $query->where('location->zone', $zone);
    }

    public function scopeByPointOfSale($query, $pointOfSaleId)
    {
        return $query->where('point_of_sale_id', $pointOfSaleId);
    }

    // --- ACCESSORS & MUTATORS ---

    /**
     * Récupère le nom lisible de la zone (ex: "Rez-de-chaussée")
     */
    public function getZoneLabelAttribute()
    {
        $zoneKey = $this->location['zone'] ?? 'rdc';
        return self::$availableZones[$zoneKey] ?? $zoneKey;
    }

    public function getDisplayNameAttribute()
    {
        return $this->name ?: "Table " . $this->table_number;
    }

    // --- METHODS ---

    /**
     * Helper pour changer la zone facilement
     */
    public function setZone(string $zoneKey)
    {
        $currentLocation = $this->location ?? [];
        $currentLocation['zone'] = $zoneKey;
        $this->location = $currentLocation;
        $this->save();
    }

    public function markAsOccupied()
    {
        $this->update(['status' => 'occupied']);
    }

    public function markAsAvailable()
    {
        $this->update(['status' => 'available']);
    }

}