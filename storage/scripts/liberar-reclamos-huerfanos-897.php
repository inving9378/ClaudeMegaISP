<?php

/**
 * Item #897 — libera en bulk los reclamos worker_sid huérfanos de items paraguas parqueados.
 *
 * Uso:
 *   php storage/scripts/liberar-reclamos-huerfanos-897.php            (dry-run: solo lista candidatos)
 *   php storage/scripts/liberar-reclamos-huerfanos-897.php --apply    (aplica la liberación)
 *
 * Idempotente: una vez liberado, el item deja de tener worker_sid → la query ya no lo
 * vuelve a traer en una corrida posterior. Correr dos veces con --apply es seguro.
 *
 * Criterio de "huérfano" (decidido por Irving en el brief del item #897):
 *   worker_sid IS NOT NULL AND status != 'done' AND estado_aprobacion NOT IN
 *   ('completado','cancelado','rechazado','en_progreso')
 * — reclamos que sobrevivieron a items ya parqueados/en espera, no a trabajo activo real.
 * Antes de tocar cualquiera se descarta el que tenga un proceso VIVO confirmado en
 * RegistroPids::todos() trabajando ese mismo item (defensa en profundidad, no debería
 * disparar nunca porque 'en_progreso' ya queda fuera de la query — pero se deja por si el
 * criterio de estado cambia en el futuro).
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Support\RegistroPids;

$apply = in_array('--apply', $argv, true);

$candidatos = RoadmapItem::whereNotNull('worker_sid')
    ->where('status', '!=', 'done')
    ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado', 'en_progreso'])
    ->get();

$vivos = collect(RegistroPids::todos())->filter(fn ($e) => $e['vivo']);

$liberados = [];
$saltados = [];

foreach ($candidatos as $item) {
    $procesoVivo = $vivos->first(fn ($e) => $e['item'] !== null && (string) $e['item'] === (string) $item->id);

    if ($procesoVivo) {
        $saltados[] = ['id' => $item->id, 'motivo' => "proceso vivo confirmado (sid={$procesoVivo['sid']})"];
        continue;
    }

    $sidPrevio = $item->worker_sid;

    if ($apply) {
        $item->worker_sid = null;
        $item->claimed_at = null;

        $log = $item->log ?: [];
        $log[] = [
            'ts' => now()->toIso8601String(),
            'por' => 'circuito:wt-1',
            'evento' => 'reclamo_liberado',
            'terminal_liberada' => $sidPrevio,
            'estado_aprobacion' => $item->estado_aprobacion,
            'motivo' => 'Liberado por circuito — reclamo huérfano, sin proceso vivo confirmado en RegistroPids.',
        ];
        $item->log = $log;
        $item->save();
    }

    $liberados[] = ['id' => $item->id, 'sid_previo' => $sidPrevio, 'estado_aprobacion' => $item->estado_aprobacion];
}

echo ($apply ? 'APLICADO' : 'DRY-RUN') . ' — liberados: ' . count($liberados) . ', saltados: ' . count($saltados) . "\n";
foreach ($liberados as $l) {
    echo "  #{$l['id']} (sid_previo={$l['sid_previo']}, estado_aprobacion={$l['estado_aprobacion']})\n";
}
if (count($saltados)) {
    echo "Saltados:\n";
    foreach ($saltados as $s) {
        echo "  #{$s['id']}: {$s['motivo']}\n";
    }
}
