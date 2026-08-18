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


    // Un point de vente a plusieurs utilisateurs (un-à-plusieurs legacy)
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Un point de vente a plusieurs utilisateurs (plusieurs-à-plusieurs)
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'point_of_sale_user', 'point_of_sale_id', 'user_id')->withTimestamps();
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
