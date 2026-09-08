<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill-switch del enganche de facturación de suscripciones de Flotas
    |--------------------------------------------------------------------------
    |
    | Nace en FALSE. Mientras esté apagado, calculateAmounts NO inyecta ninguna
    | línea de Flotas y el motor de facturación se comporta IDÉNTICO al actual
    | (cliente con/sin FleetSubscription factura igual que hoy). Solo se
    | enciende manualmente —tras validar end-to-end— vía .env.
    |
    */

    'billing_enabled' => env('FLOTAS_BILLING_ENABLED', false),

];
