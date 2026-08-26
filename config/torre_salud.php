<?php

/**
 * Item #891 (+ #884, mismo panel) — "Salud del entorno". Umbrales y rutas para el panel
 * que reemplaza el SSH manual (certificado, disco, migraciones, jobs fallidos, respaldos,
 * errores agrupados, cron schedule:run, queue workers). `env()` aquí es correcto: este
 * archivo vive en config/ y se evalúa al construir la caché (ver config:auditar-env, #790).
 */
$umbralesDisco = require __DIR__ . '/umbrales_disco.php';

return [

    // Host/puerto donde se lee el certificado TLS EN VIVO (conexión local, sin tocar
    // el filesystem de letsencrypt — /etc/letsencrypt/archive no es legible por www-data).
    'cert_host'   => env('TORRE_SALUD_CERT_HOST', 'dev.meganett.com.mx'),
    'cert_puerto' => (int) env('TORRE_SALUD_CERT_PUERTO', 443),

    // Directorio de respaldos automáticos (backup_db:process). Solo lectura.
    'backup_dir' => env('TORRE_SALUD_BACKUP_DIR', '/var/backups/mysql'),

    'umbrales' => [
        'certificado'  => ['amarillo_dias' => 30, 'rojo_dias' => 10],
        // #vigilante (2026-08-25): NO se definen aquí. El disco tiene UNA sola política y vive en
        // config/umbrales_disco.php — antes este renglón decía 85/93 mientras los escalones del
        // vigilante decían 80/85/90/95, así que el panel pintaba verde a 82 % con el otro ya
        // avisando. Amarillo = el escalón en que se avisa; rojo = aquel en que ya hay que truncar.
        'disco'        => [
            'amarillo_pct' => $umbralesDisco['escalones']['avisa'],
            'rojo_pct'     => $umbralesDisco['escalones']['trunca'],
        ],
        'jobs_fallidos' => ['rojo_cantidad' => 10, 'rojo_horas' => 24],
        'respaldo'     => ['amarillo_horas' => 36, 'rojo_horas' => 72],
        'errores_24h'  => ['amarillo' => 50, 'rojo' => 500],
        // #884 — mínimo de procesos `artisan queue:work` esperados corriendo (ver deploy/megaisp-queue.conf: 2 workers).
        'queue_workers' => ['minimo_esperado' => (int) env('TORRE_SALUD_QUEUE_WORKERS_MIN', 1)],
    ],
];
