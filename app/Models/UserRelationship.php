<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Relación Padre-Hijo entre dos cuentas `users` (item #21). Solo lectura por
 * diseño: crear la relación NO copia roles/permisos del hijo al padre — el
 * acceso lo decide, aparte y de forma explícita, quien consuma esta tabla.
 */
class UserRelationship extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'parent_user_id',
        'child_user_id',
        'type',
        'status',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function child()
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'activa');
    }
}
