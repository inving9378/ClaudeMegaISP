<?php

namespace App\Modules\Addons\Ipv6\Models;

use Illuminate\Database\Eloquent\Model;

// Modelo plano (no BaseModel): log append-only de auditoría, nunca se
// edita/borra — mismo criterio que otras tablas de auditoría del proyecto
// (p.ej. Ipv6DualStackCutoffDryRun). Sin updated_at, solo `cuando`.
class Ipv6RenumberingTransition extends Model
{
    protected $table = 'ipv6_renumbering_transitions';

    public $timestamps = false;

    protected $fillable = [
        'plan_id',
        'quien_user_id',
        'quien_nombre',
        'cuando',
        'de_estado',
        'a_estado',
        'nota',
    ];

    protected $casts = [
        'cuando' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Ipv6RenumberingPlan::class, 'plan_id');
    }
}
