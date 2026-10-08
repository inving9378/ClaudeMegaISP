<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Siembra del expediente de ejemplo de MEGANET (IMPI 2806655)
    |--------------------------------------------------------------------------
    | REGLA DURA (decisión de Irving): el expediente 2806655 NO es dato inicial
    | universal del módulo. Solo se auto-carga en la instalación de MEGANET
    | (instancia tipo "operador", vía App\Support\RolInstancia::esOperador()).
    | En la VM de cualquier otro ISP NUNCA se siembra solo: ahí solo entra por
    | importación expresa (`php artisan monitoring:import-demo-impi`).
    |
    | Valores:
    |   null  (default) → decide ModuleDefinition::install() por tipo de instancia
    |                      (true solo si esOperador()).
    |   true           → forzar la siembra aunque no sea operador (importación expresa).
    |   false          → vetar la siembra incluso en MEGANET.
    */
    'auto_seed_demo' => env('MONITOREO_AUTOSEED_DEMO', null),

    /*
    | Clave del módulo ante los servicios compartidos (IA / correo / WhatsApp) para
    | resolver la línea/cuenta asignada en el Hub cuando esos add-ons existan.
    */
    'service_key' => 'monitoreo-oficial',
];
