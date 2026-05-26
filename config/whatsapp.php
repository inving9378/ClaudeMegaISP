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

    /*
    | Modo auto-respuesta. Cuando está activo, cada mensaje entrante dispara
    | una llamada a la IA y, si la confianza de la intención supera el umbral,
    | envía automáticamente el borrador sin esperar al agente humano.
    | Por debajo del umbral solo guarda el borrador en whatsapp_messages.context
    | para revisión humana en el panel.
    | Manejar con cuidado: si el prompt de la IA falla en algún edge case
    | (sarcasmo, ambigüedad, mensaje hostil), el cliente recibirá el borrador
    | sin filtro. Mantener WHATSAPP_AUTO_REPLY=false hasta validar.
    */
    'auto_reply'                 => (bool)  env('WHATSAPP_AUTO_REPLY', false),
    'auto_reply_min_confidence'  => (float) env('WHATSAPP_AUTO_REPLY_MIN_CONFIDENCE', 0.80),
    'auto_reply_tone'            => (string) env('WHATSAPP_AUTO_REPLY_TONE', 'friendly'),
];
