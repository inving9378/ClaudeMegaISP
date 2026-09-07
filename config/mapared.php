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

];
