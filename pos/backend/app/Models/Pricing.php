<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pricing extends Model
{
    use HasFactory, \App\Models\Traits\ScopeByPos;

    protected $table = 'pricing';

    protected $fillable = [
        'point_of_sale_id',
        'product_id',
        'price',
    ];

    /**
     * Get the product that owns the pricing.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the point of sale that owns the pricing.
     */
    public function pointOfSale()
    {
        return $this->belongsTo(PointOfSale::class, 'point_of_sale_id');
    }

    // Garantit qu'un produit ne peut avoir qu'un prix par point de vente
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $existing = self::where('product_id', $model->product_id)
                ->where('point_of_sale_id', $model->point_of_sale_id)
                ->first();

            if ($existing) {
                // Met à jour le prix de l'enregistrement existant avec la nouvelle valeur
                $existing->update(['price' => $model->price]);
                // Annule la création du nouvel enregistrement pour éviter la duplication
                return false;
            }
        });
    }

}
