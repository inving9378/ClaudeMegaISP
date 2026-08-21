<?php

/**
 * Item #891 — "Consola fase 7: Salud del entorno". Umbrales y rutas para el panel
 * de salud que reemplaza el SSH manual (certificado, disco, migraciones, jobs
 * fallidos, respaldos, errores agrupados). `env()` aquí es correcto: este archivo
 * vive en config/ y se evalúa al construir la caché (ver config:auditar-env, #790).
 */
return [

    // Host/puerto donde se lee el certificado TLS EN VIVO (conexión local, sin tocar
    // el filesystem de letsencrypt — /etc/letsencrypt/archive no es legible por www-data).
    'cert_host'   => env('TORRE_SALUD_CERT_HOST', 'dev.meganett.com.mx'),
    'cert_puerto' => (int) env('TORRE_SALUD_CERT_PUERTO', 443),

    // Directorio de respaldos automáticos (backup_db:process). Solo lectura.
    'backup_dir' => env('TORRE_SALUD_BACKUP_DIR', '/var/backups/mysql'),

    'umbrales' => [
        'certificado'  => ['amarillo_dias' => 30, 'rojo_dias' => 10],
        'disco'        => ['amarillo_pct' => 85, 'rojo_pct' => 93],
        'jobs_fallidos' => ['rojo_cantidad' => 10, 'rojo_horas' => 24],
        'respaldo'     => ['amarillo_horas' => 36, 'rojo_horas' => 72],
        'errores_24h'  => ['amarillo' => 50, 'rojo' => 500],
    ],
];
