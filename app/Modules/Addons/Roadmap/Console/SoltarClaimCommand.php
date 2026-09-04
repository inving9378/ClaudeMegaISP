<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * Item #927 — RED DE ÚLTIMO RECURSO del cierre de una vuelta.
 *
 * `vuelta.sh` ya decide y suelta el item cuando `claude -p` termina mal (cualquier RC != 0, vía
 * `circuito:parquear-timeout`). Lo que ese camino NO puede cubrir es que muera el SCRIPT ENTERO:
 * SIGKILL del padre, OOM, o el kill switch a media vuelta. En esos casos el item se queda
 * `en_progreso` con el `worker_sid` pegado, el slot se lo come un reclamo sin dueño, y minutos
 * después el reaper lo reporta como «reclamo huérfano» — que es el síntoma, no la causa.
 *
 * Este comando lo llama el `trap EXIT` de `vuelta.sh` en CADA salida. Es deliberadamente tímido:
 *
 *   · sólo actúa si el item sigue `en_progreso` Y su `worker_sid` es EXACTAMENTE el de esta vuelta.
 *     Si otra terminal ya lo tomó (el sid cambió), no lo toca — soltar el claim de un tercero sería
 *     peor que el problema que arregla.
 *   · si el estado ya no es `en_progreso`, no hace nada: la vuelta cerró bien o el bloque de fin
 *     anormal ya lo parqueó.
 *   · devuelve SUCCESS siempre. Es limpieza, no una decisión: nunca debe cambiar el código de
 *     salida de la vuelta ni tumbar el trap.
 *
 * Lo devuelve a su `estado_previo_claim` (o `aprobado_revisor` si no lo hay) para que vuelva a la
 * cola, en vez de escalarlo: una vuelta que murió por causas externas no es un item problemático,
 * y mandarlo a la bandeja de Irving por un OOM sería castigarlo por algo que no hizo. El anti-bucle
 * y `parquear-timeout` siguen siendo quienes atrapan al item que de verdad gira en vacío.
 */
class SoltarClaimCommand extends Command
{
    protected $signature = 'circuito:soltar-claim
        {item : id del item cuya vuelta terminó}
        {--sid= : worker que lo reclamó; sólo se suelta si el claim sigue siendo suyo}';

    protected $description = 'Suelta el reclamo de un item si su vuelta murió sin cerrarlo (#927).';

    public function handle(): int
    {
        $id  = (int) $this->argument('item');
        $sid = trim((string) $this->option('sid'));

        $item = RoadmapItem::find($id);
        if (! $item || $sid === '') {
            return self::SUCCESS;
        }

        if ($item->estado_aprobacion !== 'en_progreso') {
            return self::SUCCESS;   // cerró bien, o ya lo parqueó el camino normal
        }

        if ((string) $item->worker_sid !== $sid) {
            return self::SUCCESS;   // ya lo tomó otra terminal: no es nuestro
        }

        $destino = in_array($item->estado_previo_claim, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true)
            ? $item->estado_previo_claim
            : 'aprobado_revisor';

        $log   = $item->log ?: [];
        $log[] = [
            'ts'     => now()->toIso8601String(),
            'por'    => 'soltar-claim',
            'evento' => 'claim_liberado_al_morir_la_vuelta',
            'estado' => $destino,
            'sid'    => $sid,
            'motivo' => "La vuelta de {$sid} terminó sin cerrar el item (muerte del proceso: kill, OOM o "
                      . 'freno a media vuelta). Se libera el reclamo y vuelve a la cola como ' . $destino
                      . ' en vez de quedarse en_progreso ocupando un slot sin nadie detrás.',
        ];

        $item->log               = $log;
        $item->estado_aprobacion = $destino;
        $item->worker_sid        = null;
        $item->claimed_at        = null;
        $item->save();

        $this->info("#{$id}: reclamo de {$sid} liberado; vuelve a la cola como {$destino}.");

        return self::SUCCESS;
    }
}
