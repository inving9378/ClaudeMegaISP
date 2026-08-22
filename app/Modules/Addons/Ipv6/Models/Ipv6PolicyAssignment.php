<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\BaseModel;
use App\Models\Client;

// BaseModel (no plano): la tabla SÍ tiene created_by/updated_by (auto-stamp).
class Ipv6PolicyAssignment extends BaseModel
{
    protected $table = 'ipv6_policy_assignments';

    protected $fillable = [
        'client_id',
        'ipv6_policy_id',
        'estado',
        'activada_en',
        'revocada_en',
        'notas',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activada_en' => 'datetime',
        'revocada_en' => 'datetime',
    ];

    public function policy()
    {
        return $this->belongsTo(Ipv6Policy::class, 'ipv6_policy_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
