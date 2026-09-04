<?php

/**
 * Item #9990109 — Fase 2b-i: harness de repro CONCURRENTE (2 procesos PHP reales) del cascade
 * sobre #32. Continuación de la Fase 1 secuencial (#9990076, commit 638ab4ae — NO reprodujo).
 *
 * Crea el fixture sintético: padre nivel-C sin merge_commit (idéntico en forma al estado real de
 * #32 justo antes del incidente) + DOS hijos con origen_item_id=padre.id, ambos "abiertos" (para
 * que tieneSubItemsAbiertos() del padre sea true mientras ninguno haya cerrado).
 *
 * Idempotente: borra cualquier fixture de una corrida previa (mismo marcador de título) antes de
 * crear uno nuevo — se puede relanzar sin acumular basura si un intento se cortó a medias.
 *
 * Uso (vía tinker, con el framework ya booteado — ver comentario del item para la receta completa):
 *   php artisan tinker --execute="require base_path('storage/app/repro9990109-setup.php');"
 *
 * Escribe storage/app/repro9990109-fixture.json con los ids + la ruta del lock file que usan
 * repro9990109-a.php / -b.php para sincronizar el arranque.
 *
 * NO toca datos reales del roadmap: todas las filas llevan el marcador '[REPRO9990109]' en el
 * título y las crea/borra este mismo harness.
 */

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

const REPRO9990109_MARCADOR = '[REPRO9990109]';

RoadmapItem::where('title', 'like', REPRO9990109_MARCADOR . '%')->delete();

$padre = new RoadmapItem();
$padre->title                   = REPRO9990109_MARCADOR . ' Padre sintético (nivel-C sin merge, paraguas)';
$padre->description             = 'Fixture sintético del item #9990109 — NO es un item real del roadmap.';
$padre->modulo                  = 'Roadmap / Circuito CC (fixture de prueba)';
$padre->status                  = 'pending';
$padre->nivel_riesgo            = 'C';
$padre->branch                  = 'circuito/item-test-9990109';
$padre->merge_commit            = null;
$padre->estado_aprobacion       = 'aprobado_irving';
$padre->excluir_pool_automatico = true;
$padre->esperando_merge_irving  = true;
$padre->save();

$hijoA = new RoadmapItem();
$hijoA->title             = REPRO9990109_MARCADOR . ' Hijo A';
$hijoA->description       = 'Fixture sintético del item #9990109 — NO es un item real del roadmap.';
$hijoA->modulo             = $padre->modulo;
$hijoA->status             = 'pending';
$hijoA->origen_item_id     = $padre->id;
$hijoA->estado_aprobacion  = 'aprobado_revisor';
$hijoA->save();

$hijoB = new RoadmapItem();
$hijoB->title             = REPRO9990109_MARCADOR . ' Hijo B';
$hijoB->description       = 'Fixture sintético del item #9990109 — NO es un item real del roadmap.';
$hijoB->modulo             = $padre->modulo;
$hijoB->status             = 'pending';
$hijoB->origen_item_id     = $padre->id;
$hijoB->estado_aprobacion  = 'aprobado_revisor';
$hijoB->save();

$lockFile = __DIR__ . '/repro9990109-go.lock';
@unlink($lockFile);

$fixture = [
    'padre_id'  => $padre->id,
    'hijo_a_id' => $hijoA->id,
    'hijo_b_id' => $hijoB->id,
    'lock_file' => $lockFile,
    'creado_at' => now()->toIso8601String(),
];

file_put_contents(__DIR__ . '/repro9990109-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT));

echo "[REPRO9990109] fixture creado:\n" . json_encode($fixture, JSON_PRETTY_PRINT) . "\n";
echo "[REPRO9990109] padre.estado_aprobacion antes del experimento: {$padre->estado_aprobacion}\n";
echo "[REPRO9990109] padre.tieneSubItemsAbiertos(): " . ($padre->tieneSubItemsAbiertos() ? 'true' : 'false') . "\n";
