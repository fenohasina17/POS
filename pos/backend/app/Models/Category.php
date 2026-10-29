<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, \App\Models\Traits\ScopeByPos;
    
    protected $fillable = [
        "name",
        'printer' ,
        'price_editable',
    ];

    protected $casts = [
        'price_editable' => 'boolean',
    ];

    // Lu par l'écran Produits (cadenas / bouton « Modifier le prix »).
    protected $appends = ['price_locked'];

    /**
     * Prix imposé par le Central pour les produits de cette catégorie. Une
     * caisse autonome (sans Central) garde la main sur tous ses prix.
     */
    public function getPriceLockedAttribute(): bool
    {
        return !empty(config('sync.central_url')) && !$this->price_editable;
    }
    
    protected $guarded = [];

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}