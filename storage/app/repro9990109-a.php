<?php

/**
 * Item #9990109 — cerrador concurrente A. Cierra el Hijo A del fixture creado por
 * repro9990109-setup.php, esperando el lock file compartido con -b.php.
 *
 * Uso (proceso independiente, en background, junto con -b.php — ver comentario del item):
 *   php artisan tinker --execute="require base_path('storage/app/repro9990109-a.php');"
 */

require __DIR__ . '/repro9990109-common.php';

repro9990109_cerrar_hijo('hijo_a_id', 'A');
