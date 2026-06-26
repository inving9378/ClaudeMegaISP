<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kill-switch del enganche de facturación de servicios contratables
    |--------------------------------------------------------------------------
    |
    | Nace en FALSE. Mientras esté apagado, calculateAmounts NO inyecta ninguna
    | línea de contratables y el motor de facturación se comporta IDÉNTICO al
    | actual (cliente sin/with suscripciones factura igual que hoy). Solo se
    | enciende manualmente —tras validar end-to-end— vía .env.
    |
    */

    'billing_enabled' => env('CONTRATABLES_BILLING_ENABLED', false),

];
