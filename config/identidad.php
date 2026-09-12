<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Doble escritura de colaborador_id (Fase 3b de #9990778 — Identidad unificada)
    |--------------------------------------------------------------------------
    |
    | Kill-switch aditivo (mismo patrón que DOMICILIACION_COBRO_LIVE_ENABLED /
    | PAGOS_RECURRENTES_CRON_ENABLED, ver CLAUDE.md). Con el flag en false (default
    | seguro) el observer y los parches raw no hacen nada — seller_id sigue siendo
    | la única fuente de verdad. Activarlo es la Fase 3d, decisión aparte de Irving
    | tras validar en dev.
    |
    */
    'doble_escritura_colaborador_id' => env('IDENTIDAD_DOBLE_ESCRITURA_COLABORADOR', false),

];
