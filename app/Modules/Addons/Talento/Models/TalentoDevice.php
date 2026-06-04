<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;

class TalentoDevice extends BaseModel
{
    protected $table = 'talento_devices';

    protected $fillable = [
        'user_id', 'device_key', 'platform', 'label',
        'approval_required', 'approved', 'bound_at', 'last_seen_at', 'revoked_at',
    ];

    protected $casts = [
        'approval_required' => 'boolean',
        'approved'          => 'boolean',
        'bound_at'          => 'datetime',
        'last_seen_at'      => 'datetime',
        'revoked_at'        => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function colaborador()
    {
        return $this->hasOneThrough(
            TalentoColaborador::class,
            User::class,
            'id',        // users.id
            'user_id',   // talento_colaboradores.user_id
            'user_id',   // talento_devices.user_id
            'id'
        );
    }

    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at')->where('approved', true);
    }
}
