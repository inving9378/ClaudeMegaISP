<?php

// Solo la clave que lee el entorno: env() aquí sobrevive a `config:cache`.
// El resto de la config vive en el módulo (mergeConfigFrom, ModuleServiceProvider).
return [
    // null = decide ModuleDefinition::install() por tipo de instancia; true fuerza; false veta.
    // #990 — alerta de pérdida de paquetes en dedicados (apagada por defecto).
    'alerta_perdida_paquetes' => env('ALERTA_PERDIDA_PAQUETES_ENABLED', false),

    'auto_seed_demo' => env('MONITOREO_AUTOSEED_DEMO', null),
];
