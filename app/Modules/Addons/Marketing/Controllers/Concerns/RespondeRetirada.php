<?php

namespace App\Modules\Addons\Marketing\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Marketing IA 360 · Fase 1 (F1-3) — respuesta de las funciones RETIRADAS.
 *
 * Campañas legado, Plantillas legado, generar copy/imagen, leads-legacy y stats dependían de tablas que
 * no existen (`campaign_contents`, `campaign_schedules`, `marketing_templates`) o de columnas que
 * `marketing_campaigns` no tiene: respondían 500. Henry decidió retirarlas (2026-11-13). Las rutas NO se
 * borran (método, URI, nombre y acción quedan iguales); el método conserva su validación y su
 * findOrFail con scope (404 para un id de otra marca) y después responde 410 con este cuerpo.
 * Ver docs/marketing/fase-1-paquetes.md §2.5.
 */
trait RespondeRetirada
{
    protected function retirada(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'error'   => 'retirada',
            'mensaje' => 'Esta función se retiró porque dependía de tablas que ya no existen. Las campañas y plantillas se rehacen en la Fase 2.',
        ], 410);
    }
}
