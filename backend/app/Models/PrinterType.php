<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrinterType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the categories for the printer type.
     */
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get the printers for the printer type.
     */
    public function printers()
    {
        return $this->hasMany(Printer::class);
    }
}
