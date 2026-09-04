<?php

/**
 * Item #9990109 — lógica compartida por repro9990109-a.php / repro9990109-b.php.
 *
 * No se invoca directo. Cada cerrador la `require`a y llama repro9990109_cerrar_hijo() con la
 * clave de SU hijo en el fixture. Ambos procesos hacen polling fino (2ms) sobre el mismo lock
 * file hasta que aparece — creado por el orquestador (bash) justo después de lanzar los dos en
 * background, para maximizar el overlap real en wall-clock (no arranques secuenciales).
 */

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

function repro9990109_cerrar_hijo(string $claveFixture, string $etiquetaProceso, int $timeoutSegundos = 30): void
{
    $fixtureFile = __DIR__ . '/repro9990109-fixture.json';
    if (! file_exists($fixtureFile)) {
        fwrite(STDERR, "[REPRO9990109][{$etiquetaProceso}] falta {$fixtureFile} — corre primero repro9990109-setup.php\n");
        exit(1);
    }

    $fixture  = json_decode(file_get_contents($fixtureFile), true);
    $hijoId   = $fixture[$claveFixture] ?? null;
    $lockFile = $fixture['lock_file'] ?? null;
    if (! $hijoId || ! $lockFile) {
        fwrite(STDERR, "[REPRO9990109][{$etiquetaProceso}] fixture.json incompleto o corrupto\n");
        exit(1);
    }

    $pid = getmypid();
    echo "[REPRO9990109][{$etiquetaProceso}] pid={$pid} esperando lock {$lockFile} (hijo_id={$hijoId})\n";

    $limite = microtime(true) + $timeoutSegundos;
    while (! file_exists($lockFile)) {
        if (microtime(true) > $limite) {
            fwrite(STDERR, "[REPRO9990109][{$etiquetaProceso}] pid={$pid} timeout esperando el lock — "
                . "el orquestador nunca hizo touch sobre {$lockFile}\n");
            exit(1);
        }
        usleep(2000); // 2ms — polling fino para no perder overlap real entre los dos procesos
    }

    $tSuelta = microtime(true);

    $item = RoadmapItem::find($hijoId);
    if (! $item) {
        fwrite(STDERR, "[REPRO9990109][{$etiquetaProceso}] pid={$pid} hijo {$hijoId} ya no existe\n");
        exit(1);
    }
    $item->estado_aprobacion = 'completado';
    $item->save();

    $tGuardado = microtime(true);

    echo sprintf(
        "[REPRO9990109][%s] pid=%d hijo=%d CERRADO — lock_visto_at=%.6f guardado_at=%.6f delta_ms=%.3f\n",
        $etiquetaProceso,
        $pid,
        $hijoId,
        $tSuelta,
        $tGuardado,
        ($tGuardado - $tSuelta) * 1000
    );
}
