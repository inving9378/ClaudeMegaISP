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
    | URL base que Evolution API usa para enviar webhooks de vuelta a Laravel.
    | Si Evolution y Laravel viven en el MISMO servidor (caso default), usa
    | localhost/127.0.0.1 — más rápido y NO depende de NAT loopback (que
    | suele estar deshabilitado en hosts con NAT 1:1, donde la IP pública
    | externa NO está bindeada localmente y conectar a ella desde el mismo
    | host da EHOSTUNREACH).
    |
    | Solo usa la IP pública aquí si Evolution corre en un host DISTINTO.
    */
    'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL', 'http://127.0.0.1'),

    'fake'             => env('WHATSAPP_FAKE', false),
];
