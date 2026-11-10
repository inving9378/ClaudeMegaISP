<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

/** Foto de un hallazgo (item #1311), reducida al guardar; los datos del EXIF quedan en columnas. */
class TalentoHallazgoFoto extends BaseModel
{
    protected $table = 'talento_hallazgo_fotos';

    protected $fillable = [
        'hallazgo_id', 'ruta', 'nombre_original', 'bytes', 'bytes_original', 'ancho', 'alto', 'sha256',
        'exif_lat', 'exif_lng', 'exif_tomada_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'hallazgo_id'    => 'integer',
        'bytes'          => 'integer',
        'bytes_original' => 'integer',
        'ancho'          => 'integer',
        'alto'           => 'integer',
        'exif_lat'       => 'float',
        'exif_lng'       => 'float',
        'exif_tomada_at' => 'datetime',
    ];

    protected $hidden = ['ruta'];

    public function hallazgo()
    {
        return $this->belongsTo(TalentoHallazgo::class, 'hallazgo_id');
    }
}
