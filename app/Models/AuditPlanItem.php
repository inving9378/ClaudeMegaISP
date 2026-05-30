<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditPlanItem extends Model
{
    protected $table = 'audit_plan_items';

    protected $guarded = [];

    protected $casts = [
        'completado'    => 'boolean',
        'completado_at' => 'datetime',
    ];
}
