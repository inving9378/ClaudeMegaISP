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

    /*
    |--------------------------------------------------------------------------
    | Corte de lectura seller_id -> colaborador_id, módulo por módulo (Fase 4 de
    | #9990778, item #9990879)
    |--------------------------------------------------------------------------
    |
    | Mismo patrón kill-switch que el de arriba, pero para LECTURAS. Con el flag
    | en false (default seguro) las queries que ya se cortaron a este flag siguen
    | leyendo directo `seller_id`, sin ningún JOIN adicional (comportamiento
    | histórico byte-idéntico). Con el flag en true, esas mismas queries resuelven
    | la identidad vía `colaborador_id -> talento_colaboradores.user_id`, con
    | fallback a `seller_id` cuando el bridge todavía no tiene colaborador_id
    | (la cobertura del backfill no es 100% — ver ColaboradorIdResolver). El
    | resultado es el mismo `users.id` en ambos casos: es un cambio de RUTA de
    | lectura, no de dato. Un módulo se corta por vez (ver CLAUDE.md, item #9990879).
    |
    */
    'lectura_colaborador_id' => env('IDENTIDAD_LECTURA_COLABORADOR_ID', false),

];
