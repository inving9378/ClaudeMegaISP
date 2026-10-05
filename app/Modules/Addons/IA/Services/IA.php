<?php

namespace App\Modules\Addons\IA\Services;

use App\Models\Core\ApiIntegration;
use App\Models\Core\ApiIntegrationProvider;
use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Services\Core\ApiIntegrationService;

/**
 * Punto único para que cualquier módulo use "su" IA.
 *
 *   $r = IA::enviar('whatsapp.ventas', $texto, [], $system, $historial, ['max_tokens' => 800]);
 *   $datos = IA::json($r['texto']);
 *
 * Qué IA usa cada módulo se decide en Integraciones → "Módulos IA": la clave
 * apunta a una integración de IA del Hub (la llave) + modelo. El protocolo
 * (claude/openai/openai_compatible/gemini) y las capacidades salen del catálogo
 * de proveedores del Hub. SIN asignación no hay IA: se lanza IANoConfigurada
 * con un mensaje claro (decisión de Irving 2026-10-02, sin respaldo implícito).
 */
class IA
{
    /** Modelo con el que el Hub prueba una llave nueva, por protocolo (barato y disponible en cualquier cuenta). */
    public const MODELO_PRUEBA = [
        'claude' => 'claude-haiku-4-5-20251001',
        'openai' => 'gpt-4o-mini',
        'gemini' => 'gemini-2.5-flash',
    ];

    /**
     * Arma el proveedor (en memoria, no se guarda) para la clave del módulo.
     */
    public static function proveedorPara(string $clave): IAProveedor
    {
        [$asig, $integracion, $catalogo] = self::resolver($clave);

        $proveedor = new IAProveedor([
            'nombre' => $integracion->name,
            'driver' => $catalogo->driver,
            'endpoint_url' => data_get($integracion->config, 'endpoint') ?: null,
            'modelo_default' => $asig->modelo,
            'soporta_imagenes' => $catalogo->soporta_imagenes,
            'config_extra' => [
                'hub_integracion' => $integracion->slug,
                'soporta_pdf' => $catalogo->soporta_pdf,
            ],
            'activo' => true,
        ]);
        $proveedor->setRelation('integracionHub', $integracion);

        return $proveedor;
    }

    /**
     * Llave, URL base y modelo de la IA asignada, para los módulos que no
     * conversan (voz: texto a voz y voz a texto, API de audio de OpenAI).
     * Se resuelve en CADA llamada: cambiar la llave en Integraciones aplica de
     * inmediato, también en procesos que corren días (el bot de voz).
     *
     * @return array{llave:?string, base:string, modelo:string, integracion:ApiIntegration, proveedor:string}
     */
    public static function credencialesPara(string $clave): array
    {
        [$asig, $integracion] = self::resolver($clave);

        return [
            'llave'       => $integracion->value,
            'base'        => self::baseOpenAI(data_get($integracion->config, 'endpoint')),
            'modelo'      => $asig->modelo,
            'integracion' => $integracion,
            'proveedor'   => $integracion->name,
        ];
    }

    /**
     * URL base de una API estilo OpenAI ("…/v1") a partir del endpoint guardado
     * en la integración, que es la URL completa de chat ("…/v1/chat/completions").
     */
    public static function baseOpenAI(?string $endpoint): string
    {
        if (!$endpoint) {
            return 'https://api.openai.com/v1';
        }
        return rtrim(preg_replace('#/chat/completions/?$#', '', trim($endpoint)), '/');
    }

    /**
     * Asignación + integración + catálogo del módulo, o IANoConfigurada con el
     * motivo exacto. Lo comparten proveedorPara() y credencialesPara().
     *
     * @return array{0:IAAsignacion, 1:ApiIntegration, 2:ApiIntegrationProvider}
     */
    protected static function resolver(string $clave): array
    {
        $modulo = self::modulo($clave) ?? [];
        $nombreModulo = $modulo['nombre'] ?? $clave;

        $asig = IAAsignacion::with('integracion')->where('clave', $clave)->first();
        $integracion = $asig?->integracion;
        if (!$asig || !$integracion) {
            $motivo = $asig ? 'tenía una IA asignada pero esa integración se borró' : 'no tiene una IA asignada';
            throw new IANoConfigurada("«{$nombreModulo}» {$motivo}. Configúralo en Integraciones → Módulos IA.");
        }

        $catalogo = ApiIntegrationProvider::where('slug', $integracion->provider)->first();
        $problema = self::problemaDeIntegracion($integracion, $catalogo, $modulo, $nombreModulo);
        if ($problema) {
            throw new IANoConfigurada($problema);
        }

        return [$asig, $integracion, $catalogo];
    }

    /**
     * ¿Por qué esta integración NO sirve para este módulo? Null si sirve.
     * Mismo criterio al asignar (Módulos IA) y al usar (resolver()).
     */
    public static function problemaDeIntegracion(ApiIntegration $integracion, ?ApiIntegrationProvider $catalogo, array $modulo, ?string $nombreModulo = null): ?string
    {
        $nombreModulo ??= $modulo['nombre'] ?? 'el módulo';
        $configurar = 'Configúralo en Integraciones → Módulos IA.';

        if (!$catalogo || $catalogo->type !== 'ia' || !$catalogo->driver) {
            return "El proveedor «{$integracion->provider}» no tiene definido su protocolo de IA. Edítalo en Integraciones → Proveedores.";
        }
        if (!$integracion->active) {
            return "La integración «{$integracion->name}» asignada a «{$nombreModulo}» está inactiva. {$configurar}";
        }
        // Un servidor propio compatible con OpenAI (Ollama) puede no pedir llave, pero sí su URL.
        $compatible = $catalogo->driver === 'openai_compatible';
        if (!$compatible && !$integracion->encrypted_value) {
            return "La integración «{$integracion->name}» asignada a «{$nombreModulo}» no tiene llave. Captúrala en Integraciones.";
        }
        if ($compatible && !data_get($integracion->config, 'endpoint')) {
            return "La integración «{$integracion->name}» no tiene la URL del servicio. Captúrala al editarla en Integraciones.";
        }

        $faltan = self::capacidadesFaltantes($catalogo, (array) ($modulo['requiere'] ?? []));
        if ($faltan) {
            return "«{$catalogo->name}» no soporta " . implode(' ni ', $faltan) . ", que «{$nombreModulo}» necesita. {$configurar}";
        }

        return null;
    }

    /**
     * Módulos (y proveedores del chat /ia) que hoy dependen de esta integración.
     * Sirve para avisar antes de borrarla o desactivarla.
     *
     * @return string[] nombres legibles
     */
    public static function usosDeIntegracion(ApiIntegration $integracion): array
    {
        $modulos = IAAsignacion::where('api_integration_id', $integracion->id)->pluck('clave')
            ->map(fn($clave) => self::modulo($clave)['nombre'] ?? $clave);

        $chat = IAProveedor::query()->get(['id', 'nombre', 'config_extra'])
            ->filter(fn($p) => data_get($p->config_extra, 'hub_integracion') === $integracion->slug)
            ->map(fn($p) => "Chat IA: {$p->nombre}");

        return $modulos->merge($chat)->values()->all();
    }

    /**
     * Entrada del catálogo config('ia_modulos') para la clave. Las claves llevan
     * punto ("whatsapp.ventas"), así que NO se puede usar config("ia_modulos.$clave"):
     * Laravel lo interpretaría como niveles anidados y nunca la encontraría.
     */
    public static function modulo(string $clave): ?array
    {
        return config('ia_modulos', [])[$clave] ?? null;
    }

    public static function para(string $clave): IAAdaptadorInterface
    {
        return IAAdaptadorFactory::crear(self::proveedorPara($clave));
    }

    /** ¿El módulo tiene una IA asignada y utilizable? (sin lanzar) */
    public static function configurada(string $clave): bool
    {
        try {
            self::proveedorPara($clave);
            return true;
        } catch (IANoConfigurada) {
            return false;
        }
    }

    /**
     * Envía el mensaje con la IA asignada al módulo. El resultado trae además
     * 'proveedor', 'driver' y 'modelo' realmente usados (para auditoría/costos).
     * El uso y costo se registran en la integración del Hub (feature = clave).
     */
    public static function enviar(string $clave, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $historial = [], array $opciones = []): array
    {
        $p = self::proveedorPara($clave);

        $r = IAAdaptadorFactory::crear($p)->enviarMensaje($historial, $mensaje, $imagenes, $systemPrompt, $opciones);

        self::registrarUso($p, $clave, $r);

        return $r + ['proveedor' => $p->nombre, 'driver' => $p->driver, 'modelo' => $p->modelo_default];
    }

    /**
     * Una vuelta de conversación con herramientas (function calling) usando la IA
     * asignada a la clave. Formato neutro (ver IAAdaptadorInterface::conversarConHerramientas):
     * si 'fin' = 'herramientas', el que llama ejecuta $r['llamadas'], agrega el turno
     * del asistente + un turno 'herramienta' por resultado, y vuelve a llamar.
     * Registra uso y costo en el Hub en cada vuelta.
     */
    public static function conversar(string $clave, array $mensajes, ?string $systemPrompt, array $herramientas, array $opciones = []): array
    {
        $p = self::proveedorPara($clave);

        $r = IAAdaptadorFactory::crear($p)->conversarConHerramientas($mensajes, $systemPrompt, $herramientas, $opciones);

        self::registrarUso($p, $clave, $r);

        return $r + ['proveedor' => $p->nombre, 'driver' => $p->driver, 'modelo' => $p->modelo_default];
    }

    /**
     * Extrae JSON de una respuesta de cualquier proveedor: tolera ```json```,
     * texto alrededor y tanto objeto {...} como arreglo [...]. Null si no hay JSON válido.
     */
    public static function json(string $texto): ?array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $texto);

        $directo = json_decode($texto, true);
        if (is_array($directo)) {
            return $directo;
        }

        $inicios = array_filter([strpos($texto, '{'), strpos($texto, '[')], fn($p) => $p !== false);
        if (!$inicios) {
            return null;
        }
        $inicio = min($inicios);
        $cierre = $texto[$inicio] === '{' ? '}' : ']';
        $fin = strrpos($texto, $cierre);
        if ($fin === false || $fin < $inicio) {
            return null;
        }
        $decodificado = json_decode(substr($texto, $inicio, $fin - $inicio + 1), true);
        return is_array($decodificado) ? $decodificado : null;
    }

    /** Uso y costo en la integración del Hub (feature = clave). Best-effort: nunca rompe la llamada. */
    protected static function registrarUso(IAProveedor $p, string $clave, array $r): void
    {
        try {
            $costo = app(IAPricingService::class)->calcularCosto(
                (string) $p->modelo_default, (int) ($r['tokens_input'] ?? 0), (int) ($r['tokens_output'] ?? 0)
            );
            self::registrarUsoIntegracion($p->getRelation('integracionHub'), $clave, $costo);
        } catch (\Throwable) {
        }
    }

    /** Uso con costo ya calculado (módulos de voz, que no cobran por tokens). Best-effort. */
    public static function registrarUsoIntegracion(ApiIntegration $integracion, string $clave, float $costoUsd): void
    {
        try {
            ApiIntegrationService::instance()->trackUsage($integracion, $clave, 1, $costoUsd);
        } catch (\Throwable) {
        }
    }

    /**
     * Traduce el error de un proveedor de IA (el cuerpo que devuelve su API) a
     * algo que entiende quien administra: motivo corto + qué hacer. Lo usan el
     * Hub al validar una llave y el botón "Probar" de Módulos IA.
     *
     * @return array{motivo:string, etiqueta:string, que_hacer:string}
     */
    public static function explicarError(string $error): array
    {
        // El orden importa: "sin saldo" llega con 429 en OpenAI, antes que "límite".
        $casos = [
            ['sin_credito', '/credit balance|insufficient_quota|exceeded your current quota|plans? (&|and) billing|billing_hard_limit/i',
                'La cuenta del proveedor no tiene saldo o agotó su cuota',
                'Recarga saldo en la cuenta del proveedor o asigna otra integración en Módulos IA.'],
            ['llave_invalida', '/invalid[ _-]?(x-)?api[ _-]?key|incorrect api key|api key not valid|API_KEY_INVALID|authentication_error|unauthorized|PERMISSION_DENIED|\b401\b/i',
                'La llave es inválida o fue revocada',
                'Copia de nuevo la llave desde la cuenta del proveedor y guárdala completa en Integraciones.'],
            ['modelo', '/model_not_found|not_found_error|does not exist|is not found|unknown model|invalid model|no such model/i',
                'El modelo no existe o la cuenta no tiene acceso a él',
                'Elige otro modelo en Módulos IA.'],
            ['limite', '/rate.?limit|too many requests|RESOURCE_EXHAUSTED|\b429\b/i',
                'El proveedor está limitando las peticiones (demasiadas seguidas)',
                'Espera un momento y vuelve a intentar; si se repite, sube el límite en la cuenta del proveedor.'],
            ['caida', '/no respondió a tiempo|timed? ?out|overloaded|server error|service unavailable|bad gateway|could not resolve|connection refused|\b50[0-9]\b/i',
                'El proveedor no respondió (caído, saturado o sin conexión)',
                'Vuelve a intentar en unos minutos; si se repite, revisa el estado del proveedor o la URL del servicio.'],
            ['formato', '/no soporta leer PDF|not supported|unsupported/i',
                'El proveedor no acepta ese tipo de archivo o de petición',
                'Asigna un proveedor que soporte lo que el módulo necesita.'],
        ];

        foreach ($casos as [$motivo, $patron, $etiqueta, $queHacer]) {
            if (preg_match($patron, $error)) {
                return ['motivo' => $motivo, 'etiqueta' => $etiqueta, 'que_hacer' => $queHacer];
            }
        }

        return ['motivo' => 'otro', 'etiqueta' => 'El proveedor devolvió un error', 'que_hacer' => 'Revisa el detalle del error.'];
    }

    /**
     * Capacidades que pide el módulo y el proveedor del catálogo NO tiene.
     * Devuelve etiquetas legibles ("leer PDF", …); vacío = cumple todo.
     */
    public static function capacidadesFaltantes(ApiIntegrationProvider $catalogo, array $requiere): array
    {
        $faltan = [];
        foreach ($requiere as $capacidad) {
            [$ok, $etiqueta] = match ($capacidad) {
                'imagenes' => [$catalogo->soporta_imagenes, 'leer imágenes'],
                'pdf' => [$catalogo->soporta_pdf, 'leer PDF'],
                // Formato neutro de herramientas: claude/openai/gemini sí; compatibles no garantizado
                'herramientas' => [in_array($catalogo->driver, ['claude', 'openai', 'gemini'], true), 'herramientas'],
                // Texto a voz / voz a texto: hoy solo la API de audio de OpenAI
                'voz' => [$catalogo->driver === 'openai', 'voz (texto a voz / voz a texto)'],
                default => [true, $capacidad],
            };
            if (!$ok) {
                $faltan[] = $etiqueta;
            }
        }
        return $faltan;
    }
}
