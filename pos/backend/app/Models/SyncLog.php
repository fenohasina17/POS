<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'status',
        'records_sent',
        'records_failed',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];
}
