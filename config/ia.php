<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Integraciones detectables para el panel de contexto de IA
    |--------------------------------------------------------------------------
    |
    | Mapa "Etiqueta" => valor, usado por ContextoProyectoService para marcar
    | qué integraciones externas parecen configuradas y mostrarlas en el panel
    | de contexto que se le entrega a la IA. Es SOLO informativo: no configura
    | ni expone la credencial, únicamente comprueba si hay un valor presente.
    |
    | IMPORTANTE: los env() SOLO deben vivir aquí (dentro de config/). Leerlos
    | con env() en tiempo de ejecución rompe cuando corre `config:cache` en
    | producción (env() devuelve null) → el panel de contexto quedaría engañoso.
    | El servicio lee este arreglo con config('ia.integraciones_detectables').
    |
    */
    'integraciones_detectables' => [
        'MikroTik'     => env('MIKROTIK_HOST'),
        'Radius'       => env('RADIUS_HOST'),
        'WhatsApp'     => env('WHATSAPP_TOKEN'),
        'Telegram'     => env('TELEGRAM_BOT_TOKEN'),
        'Google Maps'  => env('GOOGLE_MAPS_API_KEY'),
        'Stripe'       => env('STRIPE_KEY'),
        'PayPal'       => env('PAYPAL_CLIENT_ID'),
        'Mercado Pago' => env('MERCADOPAGO_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool-calling del Chat IA (item #171, #9 Fase 2)
    |--------------------------------------------------------------------------
    |
    | Kill-switch de la ejecución de acciones reales desde el Chat IA flotante
    | (IAChatController@chat, app/Http/Controllers/IA). Decisión de Irving
    | (item #171, pregunta q6): OFF por default, ON manual en dev.
    |
    | false → el chat sigue en modo Fase 1: solo informa/orienta, nunca
    |         ejecuta (comportamiento actual, sin cambio).
    | true  → habilita el compilador de tools + flujo de confirmación humana
    |         (Fase 2b, todavía sin implementar — este flag hoy no tiene
    |         ningún lector; queda listo para cuando exista).
    |
    | Flip a true SOLO por decisión explícita de Irving.
    |
    */
    'tool_calling' => [
        'enabled' => (bool) env('IA_TOOL_CALLING_ENABLED', false),
    ],

];
