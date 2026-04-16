<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Printer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cash_register_id',
        'connection_type',
        'ip_address',
        'port',
        'usb_identifier',
        'timeout',
        'is_default',
        'is_active',
        'printer_type_id'
    ];


    public function printerType()
    {
        return $this->belongsTo(PrinterType::class);
    }

    public function cashRegister()
    {
        return $this->belongsTo(CashRegister::class);
    }
}
