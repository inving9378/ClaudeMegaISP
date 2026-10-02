<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\IA\Models\IAProveedor;
use RuntimeException;

/**
 * Punto único para que cualquier módulo obtenga "su" IA.
 *
 *   IA::para('whatsapp.ventas')->enviarMensaje([], $texto, [], $system);
 *
 * Resolución: asignación de la clave → asignación 'global' → error claro.
 * El módulo nunca sabe qué proveedor hay detrás; cambiarlo es solo configuración.
 */
class IA
{
    public const GLOBAL = 'global';

    public static function proveedorPara(string $clave): IAProveedor
    {
        foreach ([$clave, self::GLOBAL] as $k) {
            $proveedor = self::proveedorDeClave($k);
            if ($proveedor) {
                return $proveedor;
            }
        }

        throw new RuntimeException("No hay proveedor de IA activo para '{$clave}' ni respaldo global.");
    }

    public static function para(string $clave): IAAdaptadorInterface
    {
        return IAAdaptadorFactory::crear(self::proveedorPara($clave));
    }

    /**
     * Envía con conmutación: si el proveedor de la clave falla y existe un
     * respaldo global distinto, reintenta con ese.
     */
    public static function enviar(string $clave, string $mensaje, array $imagenes = [], ?string $systemPrompt = null, array $historial = []): array
    {
        $primario = self::proveedorPara($clave);

        try {
            return IAAdaptadorFactory::crear($primario)->enviarMensaje($historial, $mensaje, $imagenes, $systemPrompt);
        } catch (\Throwable $e) {
            $respaldo = $clave !== self::GLOBAL ? self::proveedorDeClave(self::GLOBAL) : null;
            if (!$respaldo || $respaldo->id === $primario->id) {
                throw $e;
            }
            \Log::warning('IA: proveedor falló, usando respaldo global', [
                'clave' => $clave, 'proveedor' => $primario->nombre, 'error' => $e->getMessage(),
            ]);
            return IAAdaptadorFactory::crear($respaldo)->enviarMensaje($historial, $mensaje, $imagenes, $systemPrompt);
        }
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
