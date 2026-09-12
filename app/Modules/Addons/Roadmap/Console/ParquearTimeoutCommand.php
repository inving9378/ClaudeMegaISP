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
        {--causa=timeout : cómo terminó la vuelta: timeout|max_turns|error|sigkill|limite_cuenta (sólo para el motivo, #927/#9990416)}
        {--hora-reset= : hora de reset del límite de cuenta si se conoce (sólo con causa=limite_cuenta, #9990416); dato informativo, nunca bloqueante}
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
            // #9990416 — la cuenta de Claude se quedó sin límite de sesión: no es un fallo del
            // item ni de la vuelta, es un límite externo. Ver tercer camino más abajo.
            'limite_cuenta' => 'La cuenta de Claude se quedó sin límite de sesión',
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

        // #9990855 — GUARDA DE ESTADO (Causa A). El item vive `en_progreso` desde que se reclama
        // (RoadmapCircuitoService.php:2359) hasta que la propia vuelta lo resuelve. Si al llegar
        // aquí YA NO está en_progreso, algo DENTRO de esta misma vuelta lo resolvió antes de que
        // el corte por timeout llegara — típicamente el guard de paraguas (RoadmapItem::saving()
        // bloque 2b) al interceptar un intento de cierre con sub-items abiertos. Pisarlo aquí es
        // exactamente el bug reportado: #9990801/#9990714/#9990776/#9990810 quedaron bien
        // parqueados (aprobado_irving + excluir_pool_automatico) y segundos después este mismo
        // manejador los mandó a requiere_irving igual. Va ANTES que el resto (incluido el tercer
        // camino de `limite_cuenta`, que también podría pisar el parqueo): es la guarda más barata
        // (nada de BD ni de git) y cubre cualquier causa, incluida `sigkill`.
        if ($item->estado_aprobacion !== 'en_progreso') {
            $log      = $item->log ?: [];
            $ultimo   = $log !== [] ? end($log) : null;
            $actor    = is_array($ultimo) ? ($ultimo['por'] ?? 'desconocido') : 'desconocido';
            $evento   = is_array($ultimo) ? ($ultimo['evento'] ?? null) : null;
            $detalle  = $evento ? " ({$evento})" : '';

            $this->info("#{$id}: ya no está en_progreso (lo dejó «{$actor}»{$detalle} en "
                . "{$item->estado_aprobacion}); no se escala.");

            if ($dry) {
                $this->info('DRY: NO se escala (el estado ya lo resolvió otro actor en esta vuelta).');

                return self::SUCCESS;
            }

            $log[] = [
                'ts'      => now()->toIso8601String(),
                'por'     => 'timeout',
                'evento'  => 'timeout_no_escalado',
                'estado'  => $item->estado_aprobacion,
                'causa'   => $causa,
                'motivo'  => "{$comoTermino}, pero «{$actor}»{$detalle} ya había resuelto el item "
                    . "durante esta misma vuelta (quedó en {$item->estado_aprobacion}). El manejador "
                    . 'de timeout no pisa una decisión ya tomada (#9990855).',
            ];
            $item->log = $log;
            $item->save();

            return self::SUCCESS;
        }

        // #9990855 — GUARDA DE PARAGUAS (spec punto 5). Un item con sub-items abiertos NUNCA se
        // escala por timeout: su cierre depende de que ellos cierren (cascada), no de esta vuelta.
        // Cubre el caso en que la decomposición ya ocurrió pero AÚN no se intentó el cierre (por
        // eso `estado_aprobacion` sigue en 'en_progreso' y la guarda de arriba no la atrapa). Más
        // cara que la de estado (hace una query), por eso va segunda.
        if ($item->tieneSubItemsAbiertos()) {
            $abiertos = $item->subItemsAbiertos()->count();

            $this->info("#{$id}: tiene {$abiertos} sub-item(s) abierto(s); no se escala (cierra por cascada).");

            if ($dry) {
                $this->info('DRY: NO se escala (paraguas con sub-items abiertos).');

                return self::SUCCESS;
            }

            $log   = $item->log ?: [];
            $log[] = [
                'ts'                => now()->toIso8601String(),
                'por'               => 'timeout',
                'evento'            => 'timeout_no_escalado',
                'estado'            => 'aprobado_irving',
                'causa'             => $causa,
                'subitems_abiertos' => $abiertos,
                'motivo'            => "{$comoTermino}, pero el item tiene {$abiertos} sub-item(s) "
                    . 'abierto(s): su cierre depende de ellos (cascada), no de esta vuelta. No se '
                    . 'escala (#9990855).',
            ];
            $item->log                     = $log;
            $item->estado_aprobacion       = 'aprobado_irving';
            $item->excluir_pool_automatico = true;
            $item->worker_sid              = null;
            $item->claimed_at              = null;
            $item->save();

            return self::SUCCESS;
        }

        // #9990416 — TERCER CAMINO, paralelo a reanudar/bandeja: la cuenta de Claude se quedó sin
        // límite de sesión. NO es señal de que el item sea grande o esté atorado, así que NO toca
        // veces_timeouteo/reanudaciones_timeout (eso enseñaría a JarvisService::caberEnVuelta() que
        // un item sano es problemático) ni escalaciones_fingerprint (no es una escalación). Siempre
        // vuelve a la cola en su estado previo, sin importar si hubo avance o no.
        if ($causa === 'limite_cuenta') {
            $destino   = $circuito->estadoAprobadoPrevio($item);
            $horaReset = trim((string) $this->option('hora-reset'));

            $this->line(sprintf(
                '#%d · límite de cuenta agotado · destino=%s%s',
                $id, $destino, $horaReset !== '' ? " · reset={$horaReset}" : ''
            ));

            if ($dry) {
                $this->info('DRY: volvería a la cola sin penalización (límite de cuenta).');

                return self::SUCCESS;
            }

            $motivo = "{$comoTermino}. Vuelve a la cola como {$destino} sin contar como timeout"
                . ($horaReset !== '' ? " (reset estimado: {$horaReset})." : '.');

            $item->estado_aprobacion = $destino;
            $item->aprobado_por      = 'limite_cuenta:reanudado';
            $item->worker_sid        = null; // libera el slot: cualquier terminal puede retomarlo
            $item->claimed_at        = null;

            $log   = $item->log ?: [];
            $log[] = [
                'ts'         => now()->toIso8601String(),
                'por'        => 'limite_cuenta',
                'evento'     => 'limite_cuenta_detectado',
                'estado'     => $destino,
                'hora_reset' => $horaReset !== '' ? $horaReset : null,
                'motivo'     => $motivo,
            ];
            $item->log = $log;
            $item->save();

            $this->info("#{$id}: vuelve a {$destino} por límite de cuenta, sin penalización.");

            return self::SUCCESS;
        }

        // #9990855 — FASE 1, punto 6: confirmado EMPÍRICAMENTE (no solo por lectura) que esto ya
        // cuenta sobre `items.branch` (la rama de trabajo del item), no sobre una rama efímera del
        // cierre — esa rama efímera no existe en el código. Lo que sí se reprodujo en #9990810: el
        // `commits_rama:0` real del incidente salió de leer `items.branch` DESPUÉS de que, dentro
        // de la misma vuelta, el guard de paraguas ya había resuelto el item (su rama con 24
        // commits ya estaba mergeada) y una continuación posterior abrió una rama nueva sin
        // commits para ese mismo id. La medición era correcta sobre el dato que tenía delante; el
        // dato estaba viejo porque la decisión ya estaba tomada — lo que las dos guardas de arriba
        // cierran de raíz (si el estado ya se resolvió o el item es un paraguas, no se llega a
        // calcular `commits` en absoluto).
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
