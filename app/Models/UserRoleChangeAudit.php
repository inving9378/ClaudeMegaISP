<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Item #9991150 — auditoría dedicada de cambios de rol (append-only).
// Model plano (sin BaseModel/LogsActivity): mismo patrón que FleetGeofenceEvent/AuditoriaSenal.
class UserRoleChangeAudit extends Model
{
    protected $table = 'user_role_change_audits';

    public $timestamps = false;

    protected $fillable = [
        'actor_user_id',
        'actor_login',
        'target_user_id',
        'target_login',
        'roles_antes',
        'roles_despues',
        'ip',
        'created_at',
    ];

    protected $casts = [
        'roles_antes'   => 'array',
        'roles_despues' => 'array',
        'created_at'    => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
