<?php

namespace App\Modules\Addons\WhatsAppAgent\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catálogo editable de funciones de WhatsApp (Ventas, Cobranza, Soporte, Atención…).
 * `exclusive` = la función solo puede vivir en una línea a la vez (regla enforzada en
 * WhatsAppFunctionService, no en BD).
 */
class WhatsAppFunction extends BaseModel
{
    use SoftDeletes;

    protected $table = 'whatsapp_functions';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'exclusive',
        'color',
        'active',
        'position',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'exclusive' => 'boolean',
        'active'    => 'boolean',
        'position'  => 'integer',
    ];

    /** Líneas (instancias) que atienden esta función. */
    public function instances(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsAppInstance::class,
            'whatsapp_instance_functions',
            'function_id',
            'instance_id'
        )->withPivot('assigned_by', 'assigned_at');
    }

    /** Filas del pivote (para operar con eventos de modelo — los observers). */
    public function assignments(): HasMany
    {
        return $this->hasMany(WhatsAppInstanceFunction::class, 'function_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
