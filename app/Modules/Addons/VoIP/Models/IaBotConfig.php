<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;

class IaBotConfig extends Model
{
    protected $table = 'ia_bot_config';

    protected $fillable = [
        'enabled', 'voice', 'language', 'temperature',
        'max_turns', 'max_duration_seconds', 'timeout_seconds',
        'grupo_timbrado_name', 'greeting_customer', 'greeting_lead', 'system_prompt',
    ];

    protected $casts = [
        'enabled'     => 'boolean',
        'temperature' => 'float',
    ];

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
