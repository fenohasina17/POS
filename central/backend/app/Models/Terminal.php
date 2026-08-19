<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Terminal extends Model
{
    protected $fillable = [
        'terminal_id', 'restaurant_id', 'region', 'app_version', 'status',
        'ip_address', 'pending_sync_count', 'last_heartbeat_at', 'last_sync_at',
    ];

    protected $casts = [
        'last_heartbeat_at' => 'datetime',
        'last_sync_at'      => 'datetime',
    ];

    // Un terminal est considéré offline si aucun heartbeat depuis 2 minutes
    public function getIsOnlineAttribute(): bool
    {
        return $this->last_heartbeat_at
            && $this->last_heartbeat_at->gt(now()->subMinutes(2));
    }

    public function getComputedStatusAttribute(): string
    {
        return $this->is_online ? 'online' : 'offline';
    }

    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RemoteSale::class, 'terminal_id', 'terminal_id');
    }
}
