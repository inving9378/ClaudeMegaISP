<?php

return [
    // Dominio dedicado del Portal Cliente (item roadmap #144). En DEV se prueba
    // vía /etc/hosts (sin DNS público); en PROD será el registro A real.
    'domain' => env('PORTAL_CLIENTE_DOMAIN', 'portal.meganet.mx'),

    // Configuración → Portal Cliente (presentación administrable de las pantallas de acceso).
    'apariencia' => [
        // Disco PRIVADO donde se guardan las imágenes re-codificadas; se sirven solo por rutas
        // controladas (público: solo lo publicado; admin: con permiso).
        'disco' => env('PORTAL_CLIENTE_APARIENCIA_DISCO', 'local'),
        'directorio' => 'portal-cliente/apariencia',
        'peso_maximo_kb' => 10240,
        'pixeles_maximos' => 36_000_000,
        'calidad_webp' => 82,
        // Una imagen subida que ninguna versión llegó a usar se borra pasado este tiempo.
        'gracia_huerfanas_horas' => 24,
    ],
];
