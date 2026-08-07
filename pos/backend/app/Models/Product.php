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
