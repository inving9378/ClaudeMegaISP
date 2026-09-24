<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

class IaBotConfig extends Model
{
    protected $table = 'ia_bot_config';

    protected $fillable = [
        'enabled', 'piloto_porcentaje', 'horario_inicio', 'horario_fin', 'voice', 'language', 'temperature',
        'max_turns', 'max_duration_seconds', 'timeout_seconds',
        'grupo_timbrado_name', 'greeting_customer', 'greeting_lead', 'system_prompt',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'temperature' => 'float',
        'piloto_porcentaje' => 'integer',
    ];

    /**
     * Decide si ESTA llamada, ahora mismo, la debe atender María — el
     * "interruptor de piloto" que pide el plan de Fase 6. `enabled=false`
     * corta todo de raíz (el kill switch general, ya existía). Con
     * enabled=true, además hace falta: caer dentro del % de piloto (tirada
     * aleatoria por llamada, no por config) y estar dentro del horario.
     */
    public function debeAtenderAhora(): bool
    {
        if (! $this->enabled) {
            return false;
        }
        if ($this->piloto_porcentaje <= 0) {
            return false;
        }
        if (! $this->dentroDeHorario()) {
            return false;
        }
        if ($this->piloto_porcentaje >= 100) {
            return true;
        }
        return random_int(1, 100) <= $this->piloto_porcentaje;
    }

    public function dentroDeHorario(): bool
    {
        $ahora = now()->format('H:i:s');
        $inicio = (string) $this->horario_inicio;
        $fin = (string) $this->horario_fin;
        // Ventana normal (inicio < fin) — no contempla cruzar medianoche a
        // propósito: horario de oficina, no un turno nocturno.
        return $ahora >= $inicio && $ahora <= $fin;
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'enabled'          => false,
            'voice'            => 'nova',
            'language'         => 'es-MX',
            'temperature'      => 0.7,
            'max_turns'        => 12,
            'max_duration_seconds' => 480,
            'timeout_seconds'  => 15,
            'system_prompt'    => '',
            'greeting_customer' => 'Hola [nombre], bienvenido a Meganet. ¿En qué puedo ayudarte?',
            'greeting_lead'    => 'Hola, bienvenido a Meganet Telecomunicaciones. Soy María. ¿En qué puedo ayudarte?',
        ]);
    }
}
