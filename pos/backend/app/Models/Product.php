<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, \App\Models\Traits\ScopeByPos;
    protected $fillable = [
        'name',
        'ref',
        'category_id',
        'status',
        'image',
    ];
    protected $guarded = [];

    /**
     * Get the category that owns the product.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Prix imposé par le Central : la caisse est reliée à un Central et la
     * catégorie n'est pas ouverte à la modification par les gérants. Une
     * caisse autonome (sans Central) garde la main sur tous ses prix.
     */
    public function isPriceLocked(): bool
    {
        if (!$this->category) {
            return !empty(config('sync.central_url'));
        }

        return $this->category->price_locked;
    }

    public function getPriceLockedAttribute(): bool
    {
        return $this->isPriceLocked();
    }

    /**
     * Get the pricings for the product.
     */
    public function pricings()
    {
        return $this->hasMany(Pricing::class, 'product_id');
    }

    /**
     * The points of sale that belong to the product.
     */
    public function pointsOfSale()
    {
        return $this->belongsToMany(PointOfSale::class, 'point_of_sale_product', 'product_id', 'point_of_sale_id')->withTimestamps();
    }
}
