<?php

namespace App\Modules\Addons\Roadmap\Support;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\Marketing\Services\ClaudeApiClient;

/**
 * IA DEL SUPERVISOR (#1076) — «gastar lo mínimo en APIs», decisión de Irving.
 *
 * Punto ÚNICO por el que el supervisor del Circuito (válvula, revisor, briefs, des-trabe…) llama a
 * Claude. Tres cosas que antes cada servicio hacía a mano, o no hacía:
 *
 *   1. QUÉ MODELO. Se elige en Integraciones → Módulos IA (clave `circuito.<pieza>`); sin
 *      asignación, el ahorro por defecto de config/circuito.php → `ia.modelos`. Ningún servicio
 *      escribe un nombre de modelo.
 *   2. CON QUÉ PARÁMETROS. Los modelos 5.x rechazan con 400 lo que los 4.x aceptaban
 *      (`temperature`, `thinking: disabled` en Sonnet 5.5) y cuentan el razonamiento dentro de
 *      `max_tokens`. `parametros()` adapta la petición al modelo y a la pieza (esfuerzo, si
 *      razona, si el prompt fijo va a la caché).
 *   3. CUÁNTO COSTÓ. Cada llamada sale etiquetada con su pieza (y su item); ClaudeApiClient
 *      deja en el log `claude` el costo en USD y MXN, y `circuito:gasto-ia` lo suma.
 *
 * Un `stop_reason: refusal` lanza RespuestaNoConcluyente: un rechazo nunca es un veredicto.
 */
final class IaSupervisor
{
    public const VALVULA         = 'circuito.valvula';
    public const REVISOR         = 'circuito.revisor';
    public const SEGUNDA_OPINION = 'circuito.segunda_opinion';
    public const BRIEFS          = 'circuito.briefs';
    public const SEGURIDAD       = 'circuito.seguridad';
    public const DESTRABE        = 'circuito.destrabe';
    public const RESUMEN         = 'circuito.resumen';
    public const ASESOR          = 'circuito.asesor_cobranza';

    /**
     * Cómo llama cada pieza. `respaldo` = llave de config('circuito.ia.modelos').
     * `esfuerzo`: low para clasificar (válvula, revisor), medium para redactar briefs.
     * `pensar`: si el modelo 5.x puede razonar (adaptive) o va sin razonamiento.
     * `cachear`: el system prompt es largo y fijo (perfil de Irving + reglas) → caché de 5 min.
     */
    public const PIEZAS = [
        self::VALVULA         => ['respaldo' => 'valvula',         'esfuerzo' => 'low',    'pensar' => false, 'cachear' => false],
        self::REVISOR         => ['respaldo' => 'revisor',         'esfuerzo' => 'low',    'pensar' => true,  'cachear' => true],
        self::SEGUNDA_OPINION => ['respaldo' => 'segunda_opinion', 'esfuerzo' => 'medium', 'pensar' => true,  'cachear' => true],
        self::BRIEFS          => ['respaldo' => 'briefs',          'esfuerzo' => 'medium', 'pensar' => true,  'cachear' => true],
        self::SEGURIDAD       => ['respaldo' => 'seguridad',       'esfuerzo' => 'medium', 'pensar' => true,  'cachear' => true],
        self::DESTRABE        => ['respaldo' => 'destrabe',        'esfuerzo' => 'medium', 'pensar' => true,  'cachear' => true],
        self::RESUMEN         => ['respaldo' => 'resumen',         'esfuerzo' => 'low',    'pensar' => false, 'cachear' => false],
        self::ASESOR          => ['respaldo' => 'asesor_cobranza', 'esfuerzo' => 'medium', 'pensar' => true,  'cachear' => false],
    ];

    /** Solo si config/circuito.php no trae la pieza (no debería pasar). */
    private const ULTIMO_RECURSO = 'claude-sonnet-5-5';

    /** Techo de `max_tokens` cuando el modelo puede razonar: el razonamiento cuenta dentro. */
    private const PISO_CON_RAZONAMIENTO = ['low' => 8000, 'medium' => 12000, 'high' => 12000];

    /** Debajo de esto el system prompt no alcanza el mínimo cacheable: no se marca. */
    private const MIN_CHARS_CACHE = 1800;

    /** @var array<string,string> pieza => modelo, solo para `circuito:comparar-modelos-ia` y pruebas. */
    private static array $forzados = [];

    /** Modelo de la pieza: Módulos IA → ahorro por defecto. */
    public static function modelo(string $pieza): string
    {
        if (isset(self::$forzados[$pieza])) {
            return self::$forzados[$pieza];
        }

        // #1116: en modo económico (regla día/hora o tope diario alcanzado) manda el ahorro por defecto.
        if (ModoIa::modoEfectivo() === ModoIa::ECONOMICO) {
            return self::respaldo($pieza);
        }

        // #1115 — el modo activo (Normal/Potente) manda; Ahorro no impone nada.
        try {
            $modo = app(\App\Modules\Addons\Roadmap\Services\IaModosService::class)->modeloPara($pieza);
            if ($modo) {
                return $modo;
            }
        } catch (\Throwable) {
        }

        return self::base($pieza);
    }

    /** Modelo sin el modo (Módulos IA → ahorro): al que se vuelve si el del modo falla. */
    public static function base(string $pieza): string
    {
        return IA::modeloPara($pieza, self::respaldo($pieza));
    }

    /** Ahorro por defecto de la pieza (config/circuito.php → ia.modelos). */
    public static function respaldo(string $pieza): string
    {
        $llave  = self::PIEZAS[$pieza]['respaldo'] ?? null;
        $modelo = $llave ? config("circuito.ia.modelos.{$llave}") : null;

        return is_string($modelo) && trim($modelo) !== '' ? trim($modelo) : self::ULTIMO_RECURSO;
    }

    /**
     * Fija el modelo de una o más piezas para ESTE proceso (comparación de modelos, pruebas).
     * `[]` lo limpia. Nunca lo usa el circuito en operación.
     *
     * @param array<string,string> $mapa
     */
    public static function forzar(array $mapa): void
    {
        self::$forzados = $mapa;
    }

    /**
     * Llama a Claude para la pieza. `$params` sin `model`: lo pone el supervisor.
     *
     * @param array $contexto `item` (id del roadmap) u otras etiquetas para el log de costo.
     * @return array{texto:string, usage:array, modelo:string, stop_reason:?string}
     *
     * @throws RespuestaNoConcluyente si el modelo declinó (refusal)
     * @throws \RuntimeException      si la API falló (red, llave, saldo…)
     */
    public static function llamar(string $pieza, array $params, array $contexto = []): array
    {
        $modelo = self::modelo($pieza);
        $cfg    = self::PIEZAS[$pieza] ?? ['esfuerzo' => 'low', 'pensar' => false, 'cachear' => false];

        $enviar = fn (string $m) => (new ClaudeApiClient())->messages(
            self::parametros($m, $params, $cfg['esfuerzo'], $cfg['pensar'], $cfg['cachear']),
            array_filter(['pieza' => $pieza] + $contexto, fn ($v) => $v !== null && $v !== '')
        );

        try {
            $resp = $enviar($modelo);
            self::texto($resp);   // un refusal lanza: también cuenta como fallo del modelo
        } catch (\Throwable $e) {
            // #1115 — si el modelo venía de un modo y falla/rechaza, vuelve solo al anterior.
            $base = self::base($pieza);
            if (isset(self::$forzados[$pieza]) || $base === $modelo) {
                throw $e;
            }
            try {
                app(\App\Modules\Addons\Roadmap\Services\IaModosService::class)
                    ->registrarFallback($pieza, $modelo, $base, mb_substr($e->getMessage(), 0, 200));
            } catch (\Throwable) {
            }
            $modelo = $base;
            $resp   = $enviar($modelo);
        }

        return [
            'texto'       => self::texto($resp),
            'usage'       => (array) ($resp['usage'] ?? []),
            'modelo'      => $modelo,
            'stop_reason' => $resp['stop_reason'] ?? null,
        ];
    }

    /**
     * Adapta la petición al modelo. Pura (sin red ni BD) para poder probarla.
     *
     *  - `temperature`/`top_p`/`top_k`: solo los aceptan los Sonnet/Haiku 4.x; los 5.x y todo
     *    Opus 4.7+ responden 400.
     *  - Modelos 5.x: esfuerzo explícito. Sin razonamiento → Haiku `disabled`, Sonnet 5.5
     *    `between_tools` (su `disabled` es 400); Opus 5.5 no puede apagarlo. Como el razonamiento
     *    cuenta dentro de `max_tokens`, el techo sube para que el JSON no se corte; sin
     *    razonamiento sube 30 % por el tokenizer nuevo.
     *  - Opus 4.7 y los 4.x: como hasta hoy (sin razonamiento, sin esfuerzo).
     *  - Caché: el system prompt largo y fijo se manda como bloque con `cache_control` de 5 min.
     */
    public static function parametros(string $modelo, array $params, string $esfuerzo = 'low', bool $pensar = false, bool $cachear = false): array
    {
        $params['model'] = $modelo;

        if (! self::aceptaMuestreo($modelo)) {
            unset($params['temperature'], $params['top_p'], $params['top_k']);
        }

        $f = self::familia($modelo);
        if ($f['mayor'] >= 5) {
            $params['output_config'] = array_merge((array) ($params['output_config'] ?? []), ['effort' => $esfuerzo]);

            $apagado = $pensar ? null : self::razonamientoApagado($f);
            if ($apagado !== null) {
                $params['thinking'] = ['type' => $apagado];
            } else {
                unset($params['thinking']);   // adaptive es el default en los 5.x
            }

            $pedido = (int) ($params['max_tokens'] ?? 1024);
            $params['max_tokens'] = $apagado !== null
                ? max($pedido, (int) ceil($pedido * 1.3))
                : max($pedido, self::PISO_CON_RAZONAMIENTO[$esfuerzo] ?? 8000);
        }

        if ($cachear && is_string($params['system'] ?? null) && mb_strlen($params['system']) >= self::MIN_CHARS_CACHE) {
            $params['system'] = [[
                'type'          => 'text',
                'text'          => $params['system'],
                'cache_control' => ['type' => 'ephemeral'],
            ]];
        }

        return $params;
    }

    /**
     * Texto de la respuesta: solo bloques `text` (en los 5.x puede venir antes un bloque
     * `thinking`). Un rechazo lanza: no es un veredicto y no debe parsearse como uno.
     */
    public static function texto(array $resp): string
    {
        if (($resp['stop_reason'] ?? null) === 'refusal') {
            $cat = data_get($resp, 'stop_details.category');
            throw new RespuestaNoConcluyente('El modelo declinó responder (refusal' . ($cat ? ": {$cat}" : '')
                . '): no concluyente, no es un veredicto.');
        }

        $texto = '';
        foreach ((array) ($resp['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') {
                $texto .= $blk['text'] ?? '';
            }
        }

        return $texto;
    }

    /** ¿El modelo es de los caros que se usan solo para escalar? (Opus) */
    public static function esCaro(string $modelo): bool
    {
        return self::familia($modelo)['linea'] === 'opus';
    }

    /** Sonnet y Haiku 4.x (y anteriores) aceptan muestreo; Opus 4.7+ y toda la línea 5 no. */
    private static function aceptaMuestreo(string $modelo): bool
    {
        $f = self::familia($modelo);

        return in_array($f['linea'], ['sonnet', 'haiku'], true) && $f['mayor'] > 0 && $f['mayor'] < 5;
    }

    /** Valor de `thinking.type` para apagar el razonamiento, o null si el modelo no lo permite. */
    private static function razonamientoApagado(array $f): ?string
    {
        return match (true) {
            $f['linea'] === 'haiku'                                        => 'disabled',
            $f['linea'] === 'sonnet' && $f['mayor'] === 5 && $f['menor'] === 5 => 'between_tools',
            in_array($f['linea'], ['sonnet', 'opus'], true) && $f['mayor'] === 5 && $f['menor'] === 0 => 'disabled',
            default                                                        => null,   // Opus 5.5, Fable…: no se apaga
        };
    }

    /** @return array{linea:string, mayor:int, menor:int} */
    private static function familia(string $modelo): array
    {
        if (preg_match('/^claude-([a-z]+)-(\d{1,2})(?:-(\d{1,2}))?(?:-\d{8})?$/', trim($modelo), $m)) {
            return ['linea' => $m[1], 'mayor' => (int) $m[2], 'menor' => isset($m[3]) && $m[3] !== '' ? (int) $m[3] : 0];
        }

        return ['linea' => '', 'mayor' => 0, 'menor' => 0];
    }
}
