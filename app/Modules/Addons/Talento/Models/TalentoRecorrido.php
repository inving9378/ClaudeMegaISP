<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recorrido de planta externa (item #1311). El archivo de ruta original queda en el disco privado
 * (`archivo_ruta`); en BD solo la línea simplificada, una muestra para el recorte y los totales.
 * Ver Support\TrazaRecorrido y Services\RecorridoImportService.
 */
class TalentoRecorrido extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_recorridos';

    public const MODOS   = ['a_pie' => 'A pie', 'vehiculo' => 'En vehículo'];
    public const ESTADOS = ['borrador' => 'Borrador', 'enviado' => 'Enviado', 'evaluado' => 'Evaluado'];

    protected $fillable = [
        'colaborador_id', 'tarea_id', 'titulo', 'iniciado_por', 'modo', 'fuente', 'estado',
        'iniciado_at', 'terminado_at', 'recorte_desde', 'recorte_hasta', 'puntos_total', 'puntos',
        'distancia_m', 'geometria', 'muestra', 'bbox', 'archivo_ruta', 'archivo_nombre',
        'archivo_bytes', 'archivo_sha256', 'enviado_at', 'enviado_por', 'evaluado_at', 'evaluado_por',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'colaborador_id' => 'integer',
        'tarea_id'       => 'integer',
        'recorte_desde'  => 'integer',
        'recorte_hasta'  => 'integer',
        'puntos_total'   => 'integer',
        'puntos'         => 'integer',
        'distancia_m'    => 'integer',
        'archivo_bytes'  => 'integer',
        'geometria'      => 'array',
        'muestra'        => 'array',
        'bbox'           => 'array',
        'iniciado_at'    => 'datetime',
        'terminado_at'   => 'datetime',
        'enviado_at'     => 'datetime',
        'evaluado_at'    => 'datetime',
    ];

    protected $hidden = ['archivo_ruta'];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function hallazgos()
    {
        return $this->hasMany(TalentoHallazgo::class, 'recorrido_id')->orderBy('orden')->orderBy('id');
    }

    public function enviadoPor()
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }
}
