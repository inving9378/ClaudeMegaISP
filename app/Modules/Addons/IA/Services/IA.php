<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\IA\Models\IAProveedor;
use RuntimeException;

/**
 * Punto único para que cualquier módulo obtenga "su" IA.
 *
 *   $r = IA::enviar('whatsapp.ventas', $texto, [], $system, $historial, ['max_tokens' => 800]);
 *   $datos = IA::json($r['texto']);
 *
 * Resolución: asignación de la clave → asignación 'global' → error claro.
 * El módulo nunca sabe qué proveedor hay detrás; cambiarlo es solo configuración.
 * Lo que la clave necesita (config('ia_modulos.<clave>.requiere'): imagenes, pdf)
 * se valida: un proveedor que no lo soporta se salta en vez de fallar a medias.
 */
class IA
{
    public const GLOBAL = 'global';

    public static function proveedorPara(string $clave): IAProveedor
    {
        $requiere = (array) config("ia_modulos.{$clave}.requiere", []);

        foreach ([$clave, self::GLOBAL] as $k) {
            $proveedor = self::proveedorDeClave($k);
            if ($proveedor && self::cumple($proveedor, $requiere)) {
                return $proveedor;
            }
        }

        $extra = $requiere ? ' que soporte ' . implode(' y ', $requiere) : '';
        throw new RuntimeException("No hay proveedor de IA activo{$extra} para '{$clave}' ni respaldo global.");
    }

    public static function para(string $clave): IAAdaptadorInterface
    {
        return IAAdaptadorFactory::crear(self::proveedorPara($clave));
    }

    /**
     * Envía con conmutación: si el proveedor de la clave falla y existe un
     * respaldo global distinto (que cumpla los requisitos), reintenta con ese.
     * El resultado trae además 'proveedor', 'driver' y 'modelo' realmente usados
     * (para auditoría/costos, en vez de suponer que siempre fue Claude).
     */
    public static function enviar(string $clave, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $historial = [], array $opciones = []): array
    {
        $primario = self::proveedorPara($clave);

        try {
            return self::enviarCon($primario, $historial, $mensaje, $imagenes, $systemPrompt, $opciones);
        } catch (\Throwable $e) {
            $respaldo = $clave !== self::GLOBAL ? self::proveedorDeClave(self::GLOBAL) : null;
            $requiere = (array) config("ia_modulos.{$clave}.requiere", []);
            if (!$respaldo || $respaldo->id === $primario->id || !self::cumple($respaldo, $requiere)) {
                throw $e;
            }
            \Log::warning('IA: proveedor falló, usando respaldo global', [
                'clave' => $clave, 'proveedor' => $primario->nombre, 'error' => $e->getMessage(),
            ]);
            return self::enviarCon($respaldo, $historial, $mensaje, $imagenes, $systemPrompt, $opciones);
        }
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

    public static function cumple(IAProveedor $p, array $requiere): bool
    {
        foreach ($requiere as $capacidad) {
            $ok = match ($capacidad) {
                'imagenes' => (bool) $p->soporta_imagenes,
                'pdf' => $p->soportaPdf(),
                default => true,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    protected static function enviarCon(IAProveedor $p, array $historial, string $mensaje, array $imagenes, ?string $systemPrompt, array $opciones): array
    {
        $r = IAAdaptadorFactory::crear($p)->enviarMensaje($historial, $mensaje, $imagenes, $systemPrompt, $opciones);
        return $r + ['proveedor' => $p->nombre, 'driver' => $p->driver, 'modelo' => $p->modelo_default];
    }

    protected static function proveedorDeClave(string $clave): ?IAProveedor
    {
        $asig = IAAsignacion::with('proveedor')->where('clave', $clave)->first();
        $p = $asig?->proveedor;
        if (!$p || !$p->activo) {
            return null;
        }
        if ($asig->modelo) {
            // Override solo en memoria: se marca como "original" para que un
            // save()/update() posterior del proveedor NO lo persista.
            $p->modelo_default = $asig->modelo;
            $p->syncOriginalAttribute('modelo_default');
        }
        return $p;
    }
}
