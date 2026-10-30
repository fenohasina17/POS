<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['ref', 'name', 'category_id', 'price', 'size', 'image', 'image_hash'];

    protected $hidden = ['image'];

    protected $appends = ['has_image'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function getHasImageAttribute(): bool
    {
        return $this->image !== null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
