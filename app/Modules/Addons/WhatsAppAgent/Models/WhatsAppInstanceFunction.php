<?php

namespace App\Modules\Addons\WhatsAppAgent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivote función↔línea. Modelo plano (sin BaseModel/ActivityLog): la auditoría vive en
 * assigned_by/assigned_at. IMPORTANTE: el service opera con este MODELO (create()/delete()),
 * NO con belongsToMany attach/detach, para que los observers (regla 6 — no dejar función
 * huérfana) SÍ se disparen.
 */
class WhatsAppInstanceFunction extends Model
{
    protected $table = 'whatsapp_instance_functions';

    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'function_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function function(): BelongsTo
    {
        return $this->belongsTo(WhatsAppFunction::class, 'function_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }
}
