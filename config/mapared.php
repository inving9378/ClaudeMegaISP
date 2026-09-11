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

];
