<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoResponsibilityWindow extends Model
{
    protected $table = 'talento_responsibility_windows';
    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'client_id', 'caja_id', 'source_work_order_id',
        'starts_at', 'expires_at', 'active',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'active'     => 'boolean',
        'created_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function scopeActiveFor($query, int $clientId)
    {
        return $query->where('client_id', $clientId)
            ->where('active', true)
            ->where('expires_at', '>', now());
    }
}
