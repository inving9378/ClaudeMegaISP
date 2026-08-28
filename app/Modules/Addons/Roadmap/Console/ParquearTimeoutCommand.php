<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * QUÉ HACER CON UN ITEM CUYA VUELTA SE CORTÓ POR TIMEOUT (600 s).
 *
 * Antes esto era un tinker de una línea dentro de `vuelta.sh` que mandaba TODO a `requiere_irving`.
 * La intención era buena —un item que re-timeoutea quema 600 s de Max cada vez— pero trataba igual
 * dos casos opuestos:
 *
 *   · el item que AVANZÓ (abrió rama y commiteó): reanudarlo es barato, y `scopeOrdenCola` ya lo
 *     prioriza como "POR CONCLUIRSE / REANUDABLE". El anti-quemado estaba apagando justo el
 *     mecanismo de reanudación que el sistema ya tenía.
 *   · el item que GIRÓ EN VACÍO: reanudarlo es exactamente lo que hay que evitar.
 *
 * La regla (decisión de Irving, 2026-08-20):
 *
 *   | avance (commits > 0) | reanudaciones | resultado                                  |
 *   |----------------------|---------------|--------------------------------------------|
 *   | sí                   | < TOPE        | vuelve a `estado_previo_claim`, sigue en cola|
 *   | sí                   | >= TOPE       | bandeja de Irving, CON el motivo            |
 *   | no                   | —             | bandeja de Irving (comportamiento de antes) |
 *
 * AVANCE = commits en la rama > 0, NO que la rama exista: `git rev-list --count main..rama`. Un
 * item que abrió reclamo y no llegó a commitear nada no avanzó, y su protección de quemado queda
 * intacta — nunca se reanuda solo.
 *
 * Vive como comando y no como tinker inline para que la regla sea testeable: es la que decide si el
 * circuito re-gasta 600 s de límite, y esa no es una decisión que deba vivir en un string de bash.
 *
 * #194 — `reanudaciones_timeout` y `veces_timeouteo` NO son el mismo contador. El primero solo
 * cuenta dentro de `puedeReanudar` (exige avance): mide "cuántas veces se le dio otra vuelta". El
 * segundo se incrementa en TODO timeout real, avance o no: es la señal empírica que consume
 * `JarvisService::caberEnVuelta()` para detectar el item que gira en vacío repetidas veces. Antes
 * de esta separación, un item sin avance nunca incrementaba ningún contador y `caberEnVuelta` lo
 * evaluaba "cabe" cada vez, sin aprender nada de sus timeouts previos.
 */
class ParquearTimeoutCommand extends Command
{
    protected $signature = 'circuito:parquear-timeout
        {item : id del item cuya vuelta se cortó}
        {--segundos=600 : duración de la vuelta que se cortó (sólo para el motivo)}
        {--dry : no escribe, sólo dice qué haría}';

    protected $description = 'Decide si un item que timeouteó se reanuda (avanzó) o pasa a la bandeja de Irving.';

    /** Tope de reanudaciones automáticas antes de escalar. Conserva la protección de quemado. */
    public const TOPE_REANUDACIONES = 2;

    public function handle(RoadmapCircuitoService $circuito): int
    {
        $id   = (int) $this->argument('item');
        $segs = (int) $this->option('segundos');
        $dry  = (bool) $this->option('dry');

        $item = RoadmapItem::find($id);
        if (! $item) {
            $this->error("No existe el item #{$id}.");

            return self::FAILURE;
        }

        // Cerrado a mitad de la vuelta: no hay nada que parquear.
        if (in_array($item->estado_aprobacion, ['completado', 'cancelado'], true)) {
            $this->info("#{$id}: ya está {$item->estado_aprobacion}; no se toca.");

            return self::SUCCESS;
        }

        $commits = ! empty($item->branch) ? $circuito->commitsDeRama((string) $item->branch) : 0;
        // `null` = no se pudo preguntar a git. Se trata como SIN avance a propósito: ante la duda,
        // no re-gastar 600 s. El fail-safe protege el límite, no la comodidad.
        $avance  = ($commits ?? 0) > 0;
        $usadas  = (int) $item->reanudaciones_timeout;
        $destino = $circuito->estadoAprobadoPrevio($item);

        // Sólo se reanuda hacia un estado que de verdad vuelve a la cola. Si el previo era la propia
        // bandeja, reanudar no significaría nada.
        $puedeReanudar = $avance
            && $usadas < self::TOPE_REANUDACIONES
            && in_array($destino, ['aprobado_irving', 'aprobado_revisor', 'aprobado_claude'], true);

        $this->line(sprintf(
            '#%d · rama=%s · commits=%s · reanudaciones=%d/%d · destino=%s',
            $id, $item->branch ?: '—', $commits === null ? '¿?' : $commits,
            $usadas, self::TOPE_REANUDACIONES, $destino
        ));

        if ($dry) {
            $this->info($puedeReanudar ? 'DRY: se REANUDARÍA.' : 'DRY: iría a la bandeja de Irving.');

            return self::SUCCESS;
        }

        $log = $item->log ?: [];

        if ($puedeReanudar) {
            $item->reanudaciones_timeout = $usadas + 1;
            $item->veces_timeouteo       = (int) $item->veces_timeouteo + 1;
            $item->estado_aprobacion     = $destino;
            $item->aprobado_por          = 'timeout:reanudado';
            $item->worker_sid            = null;   // libera el slot para que cualquier terminal lo retome
            $item->claimed_at            = null;

            $log[] = [
                'ts'            => now()->toIso8601String(),
                'por'           => 'timeout:reanudado',
                'evento'        => 'timeout_reanudable',
                'estado'        => $destino,
                'commits_rama'  => $commits,
                'reanudacion'   => $item->reanudaciones_timeout,
                'tope'          => self::TOPE_REANUDACIONES,
                'motivo'        => "La vuelta se cortó a los {$segs}s pero la rama {$item->branch} tiene "
                                 . "{$commits} commit(s): hay avance real. Vuelve a la cola como {$destino} "
                                 . '(reanudación ' . $item->reanudaciones_timeout . ' de ' . self::TOPE_REANUDACIONES . ').',
            ];
            $item->log = $log;
            $item->save();

            $this->info("#{$id}: REANUDADO como {$destino} (reanudación {$item->reanudaciones_timeout}/"
                . self::TOPE_REANUDACIONES . ').');

            return self::SUCCESS;
        }

        if ($item->estado_aprobacion === 'requiere_irving') {
            $this->info("#{$id}: ya estaba en la bandeja; no se toca.");

            return self::SUCCESS;
        }

        $motivo = ! $avance
            ? "La vuelta se cortó a los {$segs}s y la rama no tiene commits: no hubo avance, así que "
              . 'no se re-encola (evita quemar otra vuelta en lo mismo).'
            : "La vuelta se cortó a los {$segs}s. Ya se reanudó {$usadas} vez(ces): el item es más "
              . 'grande que una vuelta y necesita que lo dividas o lo acotes.';

        $item->estado_aprobacion = 'requiere_irving';
        $item->aprobado_por      = 'timeout';
        $item->veces_timeouteo   = (int) $item->veces_timeouteo + 1;
        $log[] = [
            'ts'           => now()->toIso8601String(),
            'por'          => 'timeout',
            'evento'       => 'timeout_escalado',
            'estado'       => 'requiere_irving',
            'commits_rama' => $commits,
            'reanudacion'  => $usadas,
            'motivo'       => $motivo,
        ];
        $item->log = $log;
        $item->save();

        $this->warn("#{$id}: a la bandeja de Irving — {$motivo}");

        return self::SUCCESS;
    }
}
