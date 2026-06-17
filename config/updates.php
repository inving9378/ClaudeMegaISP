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
 */

return [
    'enabled'       => (bool) env('GITHUB_UPDATES_ENABLED', false),
    'repo'          => env('GITHUB_REPO', 'inving9378/ClaudeMegaISP'),
    'read_token'    => env('GITHUB_READ_TOKEN', env('GITHUB_TOKEN', '')),
    'cache_minutes' => (int) env('GITHUB_UPDATES_CACHE_MINUTES', 30),
];
