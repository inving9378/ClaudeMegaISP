<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cobertura vendible (MR-26, item roadmap #9990522)
    |--------------------------------------------------------------------------
    | Radio (en metros) del círculo de cobertura dibujado alrededor de cada NAP
    | con >=1 puerto libre. Consumido por
    | App\Modules\Addons\MapaRed\Services\MapaRedCoberturaService.
    | Override por entorno: MAPARED_COBERTURA_RADIO_METROS.
    */

    'cobertura' => [
        'radio_metros' => (int) env('MAPARED_COBERTURA_RADIO_METROS', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flujo animado — piloto (MR flujo animado Fase 1a, item roadmap #9990752)
    |--------------------------------------------------------------------------
    | Feature flag OFF por default. 'flujo_animado_pilot_route_id' fija el id de
    | la route/polyline piloto; null = sin piloto, el frontend elige la primera
    | que encuentre con líneas reales (Fase 1b/siguientes, no implementado aquí).
    */

    'flujo_animado_enabled' => filter_var(env('MAPARED_FLUJO_ANIMADO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'flujo_animado_pilot_route_id' => env('MAPARED_FLUJO_ANIMADO_PILOT_ROUTE_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Flujo animado — multi-piloto (MR flujo animado Fase 2a, item roadmap #9990754)
    |--------------------------------------------------------------------------
    | Generaliza 'flujo_animado_pilot_route_id' (un solo id) a una LISTA de rutas,
    | cada una con su propio estado/tipo. Formato de MAPARED_FLUJO_ANIMADO_PILOTS:
    | JSON de un arreglo de objetos {"route_id":..,"estado":"est-ok|est-degradado|
    | est-critico|est-caido","tipo":"troncal|derivacion"}. Ej.:
    | MAPARED_FLUJO_ANIMADO_PILOTS='[{"route_id":123,"estado":"est-ok","tipo":"troncal"}]'
    | Si falta o el JSON es inválido, queda en [] y el frontend cae al fallback de
    | Fase 1 (un solo pilotRouteId con clases fijas) — no rompe ambientes viejos.
    */

    'flujo_animado_pilots' => (function () {
        $raw = env('MAPARED_FLUJO_ANIMADO_PILOTS');
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
    })(),

];
