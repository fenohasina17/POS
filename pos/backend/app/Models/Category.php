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
    ];
    
    protected $guarded = [];

    /**
     * Get the products for the category.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}