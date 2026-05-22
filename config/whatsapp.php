<?php

return [
    /*
    | URL del servidor Evolution API tal como la ve Laravel (server-to-server).
    | Debe ser INTERNA (localhost o 127.0.0.1) — no la IP/dominio público.
    | Razón: en server con NAT loopback deshabilitado, llamar a la IP pública
    | propia desde el mismo host produce "cURL error 7: Failed to connect".
    | Ejemplo correcto: http://127.0.0.1:8080
    */
    'api_url'          => env('WHATSAPP_API_URL', 'http://127.0.0.1:8080'),

    'api_key'          => env('WHATSAPP_API_KEY', ''),
    'default_instance' => env('WHATSAPP_DEFAULT_INSTANCE', 'meganet'),

    /*
    | URL pública que Evolution API usa para enviar webhooks de vuelta a
    | Laravel (Evolution → Laravel). Aquí SÍ debe ser la IP/dominio público
    | porque Evolution se conecta desde fuera de este proceso.
    */
    'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL', 'http://localhost'),

    'fake'             => env('WHATSAPP_FAKE', false),
];
