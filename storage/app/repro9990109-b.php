<?php

/**
 * Item #9990109 — cerrador concurrente B. Cierra el Hijo B del fixture creado por
 * repro9990109-setup.php, esperando el lock file compartido con -a.php.
 *
 * Uso (proceso independiente, en background, junto con -a.php — ver comentario del item):
 *   php artisan tinker --execute="require base_path('storage/app/repro9990109-b.php');"
 */

require __DIR__ . '/repro9990109-common.php';

repro9990109_cerrar_hijo('hijo_b_id', 'B');
