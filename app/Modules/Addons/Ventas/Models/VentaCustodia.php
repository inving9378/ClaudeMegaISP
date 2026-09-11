<?php

namespace App\Modules\Addons\Ventas\Models;

use App\Models\BaseModel;
use App\Modules\Addons\Talento\Models\TalentoColaborador;

/**
 * Custodia de un prospecto por un colaborador (item #9990780, tabla creada en #9990799).
 * Cada renovación real (ciclo con un colaborador distinto) es una fila nueva, así que el
 * contador de renovaciones vive en la fila, no en el prospecto — decisión q3 del item: al
 * volver al pool y ser retomado, el nuevo ciclo arranca en 0 renovaciones sin código extra.
 */
class VentaCustodia extends BaseModel
{
    protected $table = 'ventas_custodias';

    protected $fillable = [
        'prospecto_id',
        'colaborador_id',
        'asignado_at',
        'fecha_limite',
        'renovaciones_usadas',
        'max_renovaciones',
        'estado',
        'liberada_at',
    ];

    protected $casts = [
        'asignado_at' => 'datetime',
        'fecha_limite' => 'date',
        'liberada_at' => 'datetime',
    ];

    public function prospecto()
    {
        return $this->belongsTo(VentaProspecto::class, 'prospecto_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }

    public function evidencias()
    {
        return $this->hasMany(VentaEvidencia::class, 'custodia_id');
    }

    public function tieneRenovacionesDisponibles(): bool
    {
        return $this->renovaciones_usadas < $this->max_renovaciones;
    }

    public function estaVencida(): bool
    {
        return $this->fecha_limite->isPast();
    }
}
