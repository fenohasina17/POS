<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class FinancialAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'user_name',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before'     => 'array',
        'after'      => 'array',
        'created_at' => 'datetime',
    ];

    // Les logs d'audit sont immuables — aucune modification permise
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \LogicException('Financial audit logs are immutable.');
        });
    }

    public static function record(
        Model $model,
        string $action,
        ?array $before = null,
        ?array $after = null
    ): void {
        $request = app(Request::class);
        $user    = auth()->user();

        static::create([
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'before'         => $before,
            'after'          => $after,
            'ip_address'     => $request->ip(),
            'user_agent'     => substr($request->userAgent() ?? '', 0, 255),
            'created_at'     => now(),
        ]);
    }

    public function auditable()
    {
        return $this->morphTo();
    }
}
