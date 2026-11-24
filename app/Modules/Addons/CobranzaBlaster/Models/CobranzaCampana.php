<?php

namespace App\Modules\Addons\CobranzaBlaster\Models;

use App\Models\BaseModel;

class CobranzaCampana extends BaseModel
{
    protected $table = 'cobranza_campanas';

    protected $fillable = [
        'nombre', 'tipo', 'max_canales_simultaneos', 'estado', 'fecha_inicio', 'fecha_fin',
        'hora_inicio', 'hora_fin', 'max_intentos', 'minutos_entre_intentos',
        'audio_mensaje', 'dias_vencimiento', 'notas', 'voz', 'voz_velocidad', 'voz_tono',
    ];

    protected $casts = [
        'fecha_inicio'          => 'date',
        'fecha_fin'             => 'date',
        'hora_inicio'           => 'string',
        'hora_fin'              => 'string',
        'dias_vencimiento'      => 'integer',
        'max_intentos'          => 'integer',
        'minutos_entre_intentos'=> 'integer',
        'voz_velocidad'         => 'float',
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

    /**
     * Desde el 24-nov la voz, la velocidad y el tono son de cada campaña y lo que se lee es la
     * nota. Las campañas creadas antes no tienen voz y siguen con su mensaje de siempre.
     */
    public function hablaLaNota(): bool
    {
        return $this->voz !== null;
    }

    /** Opciones de voz para CobranzaTtsService (vacío = las de siempre). */
    public function opcionesVoz(): array
    {
        return array_filter([
            'voz'       => $this->voz,
            'velocidad' => $this->voz_velocidad,
            'tono'      => $this->voz_tono,
        ], fn ($v) => $v !== null && $v !== '');
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
