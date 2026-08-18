<?php

return [
    /*
    | Fallback env() del driver de publicación "WhatsApp Status"
    | (app/Modules/Addons/Marketing/Services/Publishing/Drivers/WhatsAppStatusDriver.php).
    | Prioridad real en el driver: platform_config del canal → Hub de
    | integraciones (evolution) → estas claves como último respaldo.
    |
    | Nombres de env DISTINTOS a config/whatsapp.php (WHATSAPP_API_URL /
    | WHATSAPP_DEFAULT_INSTANCE) a propósito: este driver es histórico y
    | separado del gateway principal (item roadmap #793) — no se unificó
    | aquí, solo se movió env() a config() preservando el mismo nombre y
    | default de siempre.
    */
    'whatsapp_status_api_base' => env('WHATSAPP_API_BASE'),
    'whatsapp_status_api_key'  => env('WHATSAPP_API_KEY'),
    'whatsapp_status_instance' => env('WHATSAPP_INSTANCE', 'meganet-ventas'),
];
