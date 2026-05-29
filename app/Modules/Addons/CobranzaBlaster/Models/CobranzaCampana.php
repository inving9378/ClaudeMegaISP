<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use App\Models\BaseModel;

class CobranzaCampana extends BaseModel
{
    protected $table = 'cobranza_campanas';

    protected $fillable = [
        'nombre', 'estado', 'fecha_inicio', 'fecha_fin',
        'hora_inicio', 'hora_fin', 'max_intentos', 'minutos_entre_intentos',
        'audio_mensaje', 'dias_vencimiento', 'notas',
    ];

    protected $casts = [
        'fecha_inicio'          => 'date',
        'fecha_fin'             => 'date',
        'hora_inicio'           => 'string',
        'hora_fin'              => 'string',
        'dias_vencimiento'      => 'integer',
        'max_intentos'          => 'integer',
        'minutos_entre_intentos'=> 'integer',
    ];

    protected $appends = ['total_llamadas', 'porcentaje_completado'];

    public function llamadas()
    {
        return $this->hasMany(CobranzaLlamada::class, 'campana_id');
    }

    public function llamadasPendientes()
    {
        return $this->hasMany(CobranzaLlamada::class, 'campana_id')
            ->where('estado', 'pendiente');
    }

    public function scopeActiva($query)
    {
        return $query->where('estado', 'activa');
    }

    public function getTotalLlamadasAttribute(): int
    {
        return $this->llamadas()->count();
    }

    public function getPorcentajeCompletadoAttribute(): float
    {
        $total = $this->llamadas()->count();
        if ($total === 0) {
            return 0.0;
        }
        $completadas = $this->llamadas()->where('estado', '!=', 'pendiente')->count();
        return round(($completadas / $total) * 100, 1);
    }
}
