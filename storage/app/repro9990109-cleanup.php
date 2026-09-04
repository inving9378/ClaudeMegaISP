<?php

/**
 * Item #9990109 — limpieza del fixture sintético + archivos de estado del harness, después de
 * correr el experimento (repro9990109-setup.php + -a.php/-b.php en paralelo).
 *
 * Uso: php artisan tinker --execute="require base_path('storage/app/repro9990109-cleanup.php');"
 */

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

$borrados = RoadmapItem::where('title', 'like', '[REPRO9990109]%')->delete();

@unlink(__DIR__ . '/repro9990109-fixture.json');
@unlink(__DIR__ . '/repro9990109-go.lock');
@unlink(__DIR__ . '/repro9990109-a.out');
@unlink(__DIR__ . '/repro9990109-b.out');

echo "[REPRO9990109] limpieza: {$borrados} fila(s) sintética(s) borrada(s), archivos de estado eliminados.\n";
