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
    /**
     * Arma el proveedor (en memoria, no se guarda) para la clave del módulo.
     */
    public static function proveedorPara(string $clave): IAProveedor
    {
        $modulo = self::modulo($clave);
        $nombreModulo = $modulo['nombre'] ?? $clave;
        $configurar = 'Configúralo en Integraciones → Módulos IA.';

        $asig = IAAsignacion::with('integracion')->where('clave', $clave)->first();
        $integracion = $asig?->integracion;
        if (!$asig || !$integracion) {
            throw new IANoConfigurada("«{$nombreModulo}» no tiene una IA asignada. {$configurar}");
        }
        if (!$integracion->active || !$integracion->encrypted_value) {
            throw new IANoConfigurada("La integración «{$integracion->name}» asignada a «{$nombreModulo}» está inactiva o sin llave. {$configurar}");
        }

        $catalogo = ApiIntegrationProvider::where('slug', $integracion->provider)->first();
        if (!$catalogo || $catalogo->type !== 'ia' || !$catalogo->driver) {
            throw new IANoConfigurada("El proveedor «{$integracion->provider}» no tiene definido su protocolo de IA. Edítalo en Integraciones → Proveedores.");
        }

        $faltan = self::capacidadesFaltantes($catalogo, (array) ($modulo['requiere'] ?? []));
        if ($faltan) {
            throw new IANoConfigurada("«{$catalogo->name}» no soporta " . implode(' ni ', $faltan) . ", que «{$nombreModulo}» necesita. {$configurar}");
        }

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
            ApiIntegrationService::instance()->trackUsage($p->getRelation('integracionHub'), $clave, 1, $costo);
        } catch (\Throwable) {
        }
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
                default => [true, $capacidad],
            };
            if (!$ok) {
                $faltan[] = $etiqueta;
            }
        }
        return $faltan;
    }
}
