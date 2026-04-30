<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PointOfSale extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
    ];
    protected $table = 'point_of_sales';


    // Un point de vente a plusieurs utilisateurs
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'point_of_sale_product', 'point_of_sale_id', 'product_id')->withTimestamps();
    }
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
    public function pricing()
    {
        return $this->hasMany(Pricing::class);
    }
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

}
