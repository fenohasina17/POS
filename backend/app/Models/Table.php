<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_number',
        'name',
        'capacity',
        'status',
        'description',
        'point_of_sale_id',
        'location'
    ];

    protected $casts = [
        'location' => 'array',
        'capacity' => 'integer'
    ];

    // Relations
    public function pointOfSale()
    {
        return $this->belongsTo(PointOfSale::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // Scopes
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

    public function scopeByPointOfSale($query, $pointOfSaleId)
    {
        return $query->where('point_of_sale_id', $pointOfSaleId);
    }

    // Accessors & Mutators
    public function getDisplayNameAttribute()
    {
        return $this->name ?: $this->table_number;
    }

    public function getIsActiveAttribute()
    {
        return in_array($this->status, ['available', 'occupied', 'reserved']);
    }

    // Methods
    public function markAsOccupied()
    {
        $this->update(['status' => 'occupied']);
    }

    public function markAsAvailable()
    {
        $this->update(['status' => 'available']);
    }

    public function markAsReserved()
    {
        $this->update(['status' => 'reserved']);
    }

    public function markAsOutOfOrder()
    {
        $this->update(['status' => 'out_of_order']);
    }

    public function getCurrentSale()
    {
        return $this->sales()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->first();
    }

    public function getActiveSales()
    {
        return $this->sales()
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();
    }

    public function getSalesHistory()
    {
        return $this->sales()
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
