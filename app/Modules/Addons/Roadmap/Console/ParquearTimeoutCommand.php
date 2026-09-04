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
        {--causa=timeout : cómo terminó la vuelta: timeout|max_turns|error (sólo para el motivo, #927)}
        {--dry : no escribe, sólo dice qué haría}';

    protected $description = 'Decide si un item que timeouteó se reanuda (avanzó) o pasa a la bandeja de Irving.';

    /** Tope de reanudaciones automáticas antes de escalar. Conserva la protección de quemado. */
    public const TOPE_REANUDACIONES = 2;

    public function handle(RoadmapCircuitoService $circuito): int
    {
        $id   = (int) $this->argument('item');
        $segs = (int) $this->option('segundos');
        $dry  = (bool) $this->option('dry');

        // #927 — la CAUSA real del corte. Antes esto sólo lo invocaba el camino del timeout, así que
        // el motivo escrito en el log decía siempre «se cortó a los Ns» aunque la vuelta hubiera
        // muerto por agotar sus turnos. Quien investigaba leía la causa equivocada.
        $causa = (string) $this->option('causa');
        $comoTermino = match ($causa) {
            'max_turns' => 'La vuelta agotó sus turnos (max-turns)',
            'error'     => "La vuelta terminó con error tras {$segs}s",
            // #9990302 — el guard de vida máxima necesitó el SIGKILL de respaldo: el proceso
            // ignoró el SIGTERM del timeout normal.
            'sigkill'   => "El guard de vida máxima forzó el cierre con SIGKILL (ignoró SIGTERM tras {$segs}s)",
            default     => "La vuelta se cortó a los {$segs}s",
        };

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
        //
        // #9990302 — q3 (decisión de Irving en #9990295, opción 1): un proceso que ignoró SIGTERM y
        // necesitó el SIGKILL de respaldo del guard de vida máxima NUNCA se reanuda solo, tenga
        // avance o no — va SIEMPRE a la bandeja de Irving con traza parcial. Es la señal real de
        // "algo quedó genuinamente colgado", a diferencia del timeout normal (RC=124, SIGTERM
        // bastó), que si tiene avance sí se reanuda como siempre.
        $puedeReanudar = $causa !== 'sigkill'
            && $avance
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
                'causa'         => $causa,
                'motivo'        => "{$comoTermino} pero la rama {$item->branch} tiene "
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

        $motivo = match (true) {
            $causa === 'sigkill' => "{$comoTermino}. El guard de vida máxima escala SIEMPRE en "
                . 'este caso (no reanuda solo, tenga o no avance) — decisión de Irving en #9990295 (q3).',
            ! $avance => "{$comoTermino} y la rama no tiene commits: no hubo avance, así que "
                . 'no se re-encola (evita quemar otra vuelta en lo mismo).',
            default => "{$comoTermino}. Ya se reanudó {$usadas} vez(ces): el item es más "
                . 'grande que una vuelta y necesita que lo dividas o lo acotes.',
        };

        $item->estado_aprobacion = 'requiere_irving';
        $item->aprobado_por      = 'timeout';
        $item->veces_timeouteo   = (int) $item->veces_timeouteo + 1;
        // #927 — SOLTAR EL CLAIM TAMBIÉN AQUÍ. La rama de reanudación ya lo hacía; ésta no, así que
        // un item escalado a la bandeja conservaba su `worker_sid` y su `claimed_at` para siempre:
        // el reaper lo veía como reclamo huérfano y la Torre pintaba la terminal ocupada sin nadie
        // detrás. Un item en la bandeja no lo está trabajando ninguna terminal, por definición.
        $item->worker_sid = null;
        $item->claimed_at = null;
        $log[] = [
            'ts'           => now()->toIso8601String(),
            'por'          => 'timeout',
            'evento'       => 'timeout_escalado',
            'estado'       => 'requiere_irving',
            'commits_rama' => $commits,
            'reanudacion'  => $usadas,
            'causa'        => $causa,
            'motivo'       => $motivo,
        ];
        $item->log = $log;
        $item->save();

        $this->warn("#{$id}: a la bandeja de Irving — {$motivo}");

        return self::SUCCESS;
    }
}
