<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapAdjuntoService;
use Illuminate\Console\Command;

/**
 * #9991165 (Fase 3, puntos 12 y 13) — `deploy/circuito/vuelta.sh` lo llama en el modo POR-ITEM,
 * junto al bloque de respuestas de CIRC-02b, ANTES de lanzar `claude -p`:
 *
 *   exit 0 + bloque en stdout  → el item tiene adjuntos y TODOS están en disco: se antepone el
 *                                bloque "ADJUNTOS DE ESTE ITEM" con las rutas absolutas.
 *   exit 1 sin salida          → el item no tiene adjuntos: no se antepone nada (como respuesta-prompt).
 *   exit 2 + motivo en stderr  → FAIL-CLOSED: algún adjunto registrado NO está en disco. El guard ya
 *                                marcó el item (requiere_irving + motivo_espera=adjunto_faltante) y
 *                                avisó; vuelta.sh NO arranca la vuelta y suelta el claim.
 *
 * Es el segundo candado: el primero corre en claimNextParalelo() antes del claim. Este cubre la
 * ventana entre el claim y el arranque (y el despacho dirigido que no pase por el pool).
 */
class AdjuntosPromptCommand extends Command
{
    public const EXIT_FALTANTE = 2;

    protected $signature = 'circuito:adjuntos-prompt {id : ID del item} {--sid= : slot de terminal (traza)}';

    protected $description = '#9991165 — imprime el bloque ADJUNTOS del item (rutas absolutas) o sale 2 si falta alguno en disco (fail-closed).';

    public function handle(RoadmapAdjuntoService $svc): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            return self::FAILURE;
        }

        $faltantes = $svc->guardAdjuntosEnDisco($item, (string) ($this->option('sid') ?: 'adjuntos-prompt'));
        if ($faltantes) {
            foreach ($faltantes as $f) {
                $this->error("Adjunto #{$f['id']} «{$f['nombre']}» NO está en disco: {$f['ruta']}");
            }
            $this->error("Vuelta NO ARRANCA (fail-closed): item #{$item->id} marcado requiere_irving / adjunto_faltante.");

            return self::EXIT_FALTANTE;
        }

        $bloque = $svc->bloquePrompt($item);
        if ($bloque === null) {
            return self::FAILURE;
        }
        $this->line($bloque);

        return self::SUCCESS;
    }
}
