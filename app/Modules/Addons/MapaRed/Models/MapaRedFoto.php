<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * MR-23 fase 4c (item roadmap #9990455) — foto adjunta a un nodo o enlace del Mapa de Red,
 * vía relación polimórfica `fotoable` (MapaRedLayer/MapaRedDevice/MapaRedProyect/
 * MapaRedEnlaceServicio/MapaRedFiber). Disco 'public' (decisión Irving q1, opción 1).
 */
class MapaRedFoto extends Model
{
    protected $table = 'mapared_fotos';

    protected $fillable = [
        'fotoable_type',
        'fotoable_id',
        'path',
        'thumb_path',
        'caption',
        'orden',
        'uploaded_by',
    ];

    protected $appends = ['url', 'thumb_url'];

    public function fotoable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getThumbUrlAttribute(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : null;
    }
}
