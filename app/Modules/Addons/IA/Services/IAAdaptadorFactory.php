<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\Adaptadores\ClaudeAdaptador;
use App\Modules\Addons\IA\Services\Adaptadores\ClaudeCodeAdaptador;
use App\Modules\Addons\IA\Services\Adaptadores\GeminiAdaptador;
use App\Modules\Addons\IA\Services\Adaptadores\OpenAIAdaptador;
use InvalidArgumentException;

class IAAdaptadorFactory
{
    /**
     * Mapa driver => class. Para registrar un nuevo proveedor solo se
     * agrega aquí su clase (debe implementar IAAdaptadorInterface).
     *
     * 'claude_code' (2026-09-28): usa el CLI Claude Code ya autenticado en
     * este servidor en vez de una api_key medida — solo texto, sin
     * imágenes, y comparte la cuota OAuth del propio Circuito CC. Pensado
     * para dev/pruebas; producción usa 'claude'/'openai' con api_key real
     * (ver docblock de ClaudeCodeAdaptador).
     */
    protected static array $registro = [
        'claude' => ClaudeAdaptador::class,
        'claude_code' => ClaudeCodeAdaptador::class,
        'openai' => OpenAIAdaptador::class,
        'openai_compatible' => OpenAIAdaptador::class,
        'gemini' => GeminiAdaptador::class,
    ];

    public static function registrar(string $driver, string $clase): void
    {
        static::$registro[$driver] = $clase;
    }

    public static function crear(IAProveedor $proveedor): IAAdaptadorInterface
    {
        $driver = $proveedor->driver;

        if (!isset(static::$registro[$driver])) {
            throw new InvalidArgumentException("Driver de IA no registrado: {$driver}");
        }

        $clase = static::$registro[$driver];
        return new $clase($proveedor);
    }

    public static function driversDisponibles(): array
    {
        return array_keys(static::$registro);
    }
}
