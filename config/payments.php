<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identificación por ID de cliente — certeza (Fase 3 / conciliación IA)
    |--------------------------------------------------------------------------
    |
    | Cuando el cliente se identifica dando su NÚMERO DE CLIENTE (clients.id),
    | este flag decide la certeza con que queda marcada la identificación:
    |
    |   false (default) → 'proposed'  → NO auto-aplica; requiere confirmación
    |                                    humana en Fase 4 (cola de conciliación).
    |   true            → 'exact'     → auto-aplicable como el MEG.
    |
    | Arranca en false a propósito. Se cambia SOLO por config/env (variable
    | PAYMENTS_ID_CLIENTE_AUTO_APPLY), sin reprogramar nada.
    |
    */
    'id_cliente_auto_apply' => env('PAYMENTS_ID_CLIENTE_AUTO_APPLY', false),

];
