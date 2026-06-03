<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SQL Guard (WAF ligero anti-inyección)
    |--------------------------------------------------------------------------
    |
    | Defensa en profundidad: el middleware SqlInjectionProtection escanea cada
    | request en busca de firmas de inyección SQL de ALTA PRECISIÓN (UNION SELECT,
    | queries apiladas destructivas, tautologías, funciones time-based, acceso a
    | archivos, information_schema, etc.). La defensa PRIMARIA contra inyección por
    | nombre de columna/modelo vive en App\Support\Security\QueryGuard.
    |
    */

    // Desactiva el middleware por completo.
    'enabled' => env('SQL_GUARD_ENABLED', true),

    // true  = bloquea (HTTP 403) ante un match.
    // false = modo observación: solo registra en el log, deja pasar el request.
    'block' => env('SQL_GUARD_BLOCK', true),

    // Canal de log donde se registran los intentos detectados.
    'log_channel' => env('SQL_GUARD_LOG_CHANNEL', 'stack'),

    /*
    | Prefijos de ruta que se OMITEN del escaneo (patrones de Request::is()).
    | Rutas cuyo contenido puede ser legítimamente "tipo SQL/código":
    |   - webhooks externos firmados (HMAC) que no debemos reescribir/rechazar,
    |   - chat IA / devtools / smart-import donde el usuario pega SQL a propósito.
    */
    'except_routes' => [
        'webhooks/*',
        'ia/*',
        'chat-ia/*',
        'devtools/*',
        'administracion/devtools/*',
        'configuracion/smart-import*',
    ],

    /*
    | Nombres de campo (case-insensitive) que se OMITEN del escaneo. Campos de
    | texto libre / código donde símbolos SQL aparecen de forma legítima.
    */
    'except_fields' => [
        'password', 'password_confirmation', 'passwordconfirmation', 'old_password',
        'prompt', 'message', 'mensaje', 'body', 'content', 'contenido',
        'descripcion', 'description', 'observaciones', 'observacion',
        'comentario', 'comentarios', 'comments', 'nota', 'notas',
        'template', 'plantilla', 'sql', 'query', 'script', 'payload',
    ],
];
