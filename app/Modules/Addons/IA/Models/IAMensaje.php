<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IAMensaje extends BaseModel
{
    protected $table = 'ia_mensajes';

    protected $fillable = [
        'ia_conversacion_id',
        'rol',
        'contenido',
        'imagenes',
        'metadata',
        'tokens_input',
        'tokens_output',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'imagenes' => 'array',
        'metadata' => 'array',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(IAConversacion::class, 'ia_conversacion_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(IAMessageFile::class, 'ia_mensaje_id');
    }
}
