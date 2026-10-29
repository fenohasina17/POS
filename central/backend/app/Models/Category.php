<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'price_editable'];

    protected $casts = [
        'price_editable' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
