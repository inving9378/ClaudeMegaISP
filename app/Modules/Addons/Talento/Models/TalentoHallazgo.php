<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hallazgo de un recorrido (item #1311): categoría, severidad, descripción y ubicación. La
 * evaluación del supervisor (descartar o convertir en órdenes) es del item #1312.
 */
class TalentoHallazgo extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_hallazgos';

    public const SEVERIDADES = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'critica' => 'Crítica'];
    public const FUENTES     = ['exif' => 'Foto (GPS)', 'por_hora' => 'Hora de la foto', 'manual' => 'A mano', 'gps' => 'GPS de la app'];

    protected $fillable = [
        'recorrido_id', 'categoria_id', 'severidad', 'descripcion', 'lat', 'lng', 'precision_m',
        'fuente_ubicacion', 'capturado_at', 'orden', 'estado', 'evaluado_por', 'evaluado_at',
        'motivo_descarte', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'recorrido_id' => 'integer',
        'categoria_id' => 'integer',
        'lat'          => 'float',
        'lng'          => 'float',
        'precision_m'  => 'integer',
        'orden'        => 'integer',
        'capturado_at' => 'datetime',
        'evaluado_at'  => 'datetime',
    ];

    public function recorrido()
    {
        return $this->belongsTo(TalentoRecorrido::class, 'recorrido_id');
    }

    public function categoria()
    {
        return $this->belongsTo(TalentoHallazgoCategoria::class, 'categoria_id');
    }

    public function fotos()
    {
        return $this->hasMany(TalentoHallazgoFoto::class, 'hallazgo_id')->orderBy('id');
    }

    public function ordenes()
    {
        return $this->hasMany(TalentoHallazgoOrden::class, 'hallazgo_id');
    }

    /** Lo que le falta para poder enviar el recorrido (vacío = completo). */
    public function faltantes(): array
    {
        $faltan = [];
        if (! $this->categoria_id) {
            $faltan[] = 'categoría';
        }
        if (! $this->severidad) {
            $faltan[] = 'severidad';
        }
        if (trim((string) $this->descripcion) === '') {
            $faltan[] = 'descripción';
        }
        if ($this->lat === null || $this->lng === null) {
            $faltan[] = 'ubicación';
        }

        return $faltan;
    }
}
