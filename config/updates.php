<?php

/*
 * Configuración del sistema de auto-actualización de instancias (modelo PULL).
 *
 * enabled         — true solo en instancias consumidoras (GITHUB_UPDATES_ENABLED=true).
 *                   En el publicador (dev) debe ser false.
 * repo            — repositorio GitHub en formato "owner/repo".
 * read_token      — token de solo lectura para consultar la API de releases.
 *                   Puede ser el mismo GITHUB_TOKEN o uno separado GITHUB_READ_TOKEN.
 * cache_minutes   — cuántos minutos cachear el resultado del chequeo de versión.
 * manual_check_button — muestra el botón "Buscar actualizaciones" junto al banner
 *                   (solo en consumidoras). Para ocultarlo sin tocar código:
 *                   UPDATES_MANUAL_CHECK_BUTTON=false en el .env del consumidor + config:cache.
 */

return [
    'enabled'             => (bool) env('GITHUB_UPDATES_ENABLED', false),
    'repo'                => env('GITHUB_REPO', 'inving9378/ClaudeMegaISP'),
    'read_token'          => env('GITHUB_READ_TOKEN', env('GITHUB_TOKEN', '')),
    'cache_minutes'       => (int) env('GITHUB_UPDATES_CACHE_MINUTES', 30),
    // Un fallo de consulta (red/token/rate-limit) se cachea muy poco: es transitorio y el
    // usuario espera que reintentar sirva de algo. Ver item #529.
    'error_cache_minutes' => (int) env('GITHUB_UPDATES_ERROR_CACHE_MINUTES', 2),
    'manual_check_button' => (bool) env('UPDATES_MANUAL_CHECK_BUTTON', true),
];
