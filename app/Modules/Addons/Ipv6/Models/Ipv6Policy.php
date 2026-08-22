<?php

namespace App\Modules\Addons\Ipv6\Models;

use App\Models\BaseModel;

// BaseModel (no plano): la tabla SÍ tiene created_by/updated_by (auto-stamp).
class Ipv6Policy extends BaseModel
{
    protected $table = 'ipv6_policies';

    protected $fillable = [
        'nombre',
        'descripcion',
        'modo_entrante',
        'rate_in_mbps',
        'rate_out_mbps',
        'acl_profile',
        'excepciones',
        'estado',
        'retirada_en',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'excepciones' => 'array',
        'retirada_en' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(Ipv6PolicyAssignment::class, 'ipv6_policy_id');
    }
}
