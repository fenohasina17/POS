<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderLine extends Model
{
    use HasFactory;
    // Les attributs qui peuvent être assignés en masse
    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'total',
        'service_type', // sur_place | a_emporter (Sale::SERVICE_TYPES)
    ];

    // Relation avec le modèle Sale
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
    public function product()
{
    return $this->belongsTo(Product::class);
}

}
