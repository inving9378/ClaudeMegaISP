<?php

namespace App\Modules\Addons\IA\Models;
use App\Models\BaseModel;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IAMessageFile extends BaseModel
{
    protected $table = 'ia_message_files';

    protected $fillable = [
        'ia_mensaje_id',
        'path',
        'nombre_original',
        'tipo_mime',
        'tamanio',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tamanio' => 'integer',
    ];

    protected $appends = ['url', 'es_imagen'];

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(IAMensaje::class, 'ia_mensaje_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getEsImagenAttribute(): bool
    {
        return str_starts_with((string) $this->tipo_mime, 'image/');
    }
}
