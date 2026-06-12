<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrupoTimbrado extends Model
{
    protected $table = 'voip_grupos_timbrado';

    protected $fillable = [
        'nombre', 'estrategia', 'ring_time', 'destino_fallback', 'activo',
    ];

    protected $casts = [
        'activo'    => 'boolean',
        'ring_time' => 'integer',
    ];

    public function extensiones(): BelongsToMany
    {
        return $this->belongsToMany(
            Extension::class,
            'voip_grupo_extension',
            'grupo_id',
            'extension_id'
        )->withPivot('orden')->orderByPivot('orden');
    }
}
