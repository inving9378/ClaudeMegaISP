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

    /*
    |--------------------------------------------------------------------------
    | Conciliación por WhatsApp — interruptores de seguridad (Fase 3.5)
    |--------------------------------------------------------------------------
    |
    | wa_conciliation (default FALSE): interruptor MAESTRO del ruteo de pagos en
    | los mensajes entrantes. Con FALSE, ProcessIncomingMessageJob se comporta
    | EXACTAMENTE como hoy (el enganche es un no-op) y todo va al bot de ventas.
    | Con TRUE, un mensaje detectado como pago se desvía al flujo de conciliación.
    |
    | wa_autorespond (default FALSE): si el flujo de conciliación ENVÍA o no
    | respuestas reales por WhatsApp. Con FALSE, la sesión se crea y procesa pero
    | NO se manda ningún WhatsApp (la IA "calla"). Se enciende solo cuando Irving
    | lo decida. Independiente del maestro: se puede rutear en silencio.
    |
    */
    'wa_conciliation' => env('PAYMENTS_WA_CONCILIATION', false),
    'wa_autorespond'  => env('PAYMENTS_WA_AUTORESPOND', false),

    /*
    |--------------------------------------------------------------------------
    | Freno MAESTRO de aplicación automática (Fase 4) — mueve dinero
    |--------------------------------------------------------------------------
    |
    | auto_apply_enabled (default FALSE): mientras esté en false, NINGÚN pago se
    | aplica AUTOMÁTICAMENTE, aunque la identificación sea exacta (MEG). La Fase 4
    | identifica y prepara todo, pero se detiene antes de mover saldo. Se enciende
    | (PAYMENTS_AUTO_APPLY_ENABLED=true) solo cuando Irving lo decida.
    |
    | NO afecta la aplicación CONFIRMADA por un humano (Tere en Fase 6): esa es
    | una acción humana deliberada, no "automática".
    |
    */
    'auto_apply_enabled' => env('PAYMENTS_AUTO_APPLY_ENABLED', false),

];
