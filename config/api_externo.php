<?php

/*
| Item #1159 — consultas públicas para sistemas de fuera (hoy: cobertura para el asistente
| «Sofía» que corre en Vercel). Se entra con una clave en `Authorization: Bearer <clave>`;
| las claves viven en `api_externo_claves` (solo su huella sha256) y se manejan con
| `php artisan api-externo:clave`.
*/
return [
    // Peticiones por minuto que acepta cada clave antes de contestar 429.
    'limite_por_clave_minuto' => (int) env('API_EXTERNO_LIMITE_CLAVE_MINUTO', 60),

    // Peticiones por minuto desde una misma IP, con o sin clave válida (frena a quien prueba claves).
    'limite_por_ip_minuto' => (int) env('API_EXTERNO_LIMITE_IP_MINUTO', 120),
];
