<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;
use App\Models\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IAConversacion extends BaseModel
{
    protected $table = 'ia_conversaciones';

    protected $fillable = [
        'titulo',
        'ia_proyecto_id',
        'ia_proveedor_id',
        'modelo',
        'user_id',
        'ultimo_mensaje_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ultimo_mensaje_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(IAProyecto::class, 'ia_proyecto_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(IAProveedor::class, 'ia_proveedor_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(IAMensaje::class, 'ia_conversacion_id')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
