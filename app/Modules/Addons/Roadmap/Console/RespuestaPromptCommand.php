<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * CIRC-02b PASO 3 — arma (si hay) el bloque "RESPUESTA DE IRVING" a anteponer, con precedencia,
 * al prompt del ejecutor on-box. `deploy/circuito/vuelta.sh` lo llama en el modo POR-ITEM, antes
 * de armar `PROMPT_TEXT` desde `prompt-item.txt`.
 *
 * Solo LEE `roadmap_item_respuestas` sin consumir — nunca las marca aquí. El consumo real
 * ocurre al cerrar el item de verdad: `RoadmapItem::consumirRespuestasPendientes()`, llamado
 * desde `RoadmapController::decidir()` y `MergeRunner::markMerged()`. Si el item no cierra en
 * esta vuelta (se descompone, colisiona, timeoutea), la respuesta sigue sin consumir y se
 * reinyecta en la siguiente vuelta — es el comportamiento correcto: sigue vigente hasta que el
 * item de verdad cierre.
 *
 * Sin respuestas pendientes: no imprime nada y sale con código 1 (vuelta.sh lo captura con
 * `|| true` y simplemente no antepone nada). Con respuestas: imprime el bloque a stdout y
 * sale 0.
 */
class RespuestaPromptCommand extends Command
{
    protected $signature = 'circuito:respuesta-prompt {id : ID del item}';

    protected $description = 'Imprime el bloque de respuesta(s) de Irving sin consumir (si hay) para anteponer al prompt del ejecutor, con precedencia.';

    public function handle(): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            return self::FAILURE;
        }

        $respuestas = $item->respuestasSinConsumir()->orderBy('created_at')->get();
        if ($respuestas->isEmpty()) {
            return self::FAILURE;
        }

        $lineas = $respuestas->map(function ($r) {
            $fecha = $r->created_at?->format('Y-m-d H:i') ?? '?';

            return "[{$fecha} -- {$r->autor}]: {$r->cuerpo}";
        })->implode("\n");

        $this->line(
            "## RESPUESTA DE IRVING -- instrucción vigente\n"
            . $lineas . "\n"
            . 'Esta respuesta MANDA sobre el texto original. Si se contradicen, gana la respuesta. '
            . 'Si dice que algo no se hace, cierras en rechazado con la razón, no en completado.'
        );

        return self::SUCCESS;
    }
}
