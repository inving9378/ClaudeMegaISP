<?php

/**
 * Item #891 (+ #884, mismo panel) — "Salud del entorno". Item #9990968 (CIRC-09 Fase 1) dio de
 * baja la pestaña completa (disco, migraciones, jobs fallidos, respaldos, errores agrupados,
 * cron schedule:run) — solo sobreviven los umbrales que siguen teniendo consumidor real: el
 * certificado TLS (banner de la cabecera de la Torre, item #891 §3) y `queue_workers`, que
 * consume `JarvisVigilarCommand` de forma independiente al panel. `env()` aquí es correcto: este
 * archivo vive en config/ y se evalúa al construir la caché (ver config:auditar-env, #790).
 */
return [

    // Host/puerto donde se lee el certificado TLS EN VIVO (conexión local, sin tocar
    // el filesystem de letsencrypt — /etc/letsencrypt/archive no es legible por www-data).
    'cert_host'   => env('TORRE_SALUD_CERT_HOST', 'dev.meganett.com.mx'),
    'cert_puerto' => (int) env('TORRE_SALUD_CERT_PUERTO', 443),

    'umbrales' => [
        'certificado'  => ['amarillo_dias' => 30, 'rojo_dias' => 10],
        // #884 — mínimo de procesos `artisan queue:work` esperados corriendo (ver deploy/megaisp-queue.conf: 2 workers).
        'queue_workers' => ['minimo_esperado' => (int) env('TORRE_SALUD_QUEUE_WORKERS_MIN', 1)],
        // #9991088 (Fase 3 de #9991086) — minutos desde el último commit de main a partir de los
        // cuales un checkout principal sucio deja de ser "posible sync en curso" (amarillo) y pasa
        // a ser desync real (rojo).
        'checkout_principal' => ['rojo_minutos' => (int) env('TORRE_SALUD_CHECKOUT_PRINCIPAL_ROJO_MIN', 10)],
    ],
];
