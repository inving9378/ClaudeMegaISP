<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\BarridoService;
use Illuminate\Console\Command;

/**
 * MODO BARRIDO (Torre 24/7 Pieza 5b, #908) — punto de entrada real del ciclo completo:
 * `tomarCandado` → `elegirModulo` → `explorar` → crear hallazgos (o "sin hallazgos") →
 * `marcarBarrido` → `liberarCandado` (#9990033, FASE 2b-ii). Antes de este comando el servicio
 * existía pero nadie lo invocaba — el pool sólo llamaba a `AuditorService`/`circuito:auditor`.
 *
 * DRY-RUN POR DEFAULT, igual que `circuito:auditor`: sin `--apply` NO toma el candado ni escribe
 * nada, sólo reporta qué módulo tocaría y qué encontraría.
 *
 *   php artisan circuito:barrido                    # dry-run: ¿debería barrer? ¿qué encontraría?
 *   php artisan circuito:barrido --apply --sid=wt-3  # en vivo: toma candado, crea hallazgos, libera
 *   php artisan circuito:barrido --apply --forzar    # ignora el gating de debeBarrer() (cola/racha/slots)
 */
class BarridoCommand extends Command
{
    protected $signature = 'circuito:barrido
        {--apply : Toma el candado, crea los items y libera. Sin esto = DRY-RUN (no escribe nada)}
        {--forzar : Ignora el gating de debeBarrer() (cola/racha/slots) — el candado single-flight se respeta igual}
        {--sid=cli : worker_sid dueño del candado (identifica quién barrió en el log)}';

    protected $description = 'Modo barrido (#908): explora un módulo SOLO LECTURA y crea sus hallazgos como items.';

    public function handle(BarridoService $barrido): int
    {
        $g = $barrido->debeBarrer();
        $this->line('');
        $this->line('<options=bold>MODO BARRIDO (#908)</>');
        $this->line(sprintf(
            '  cola=%d (máx %d)  ·  racha seca=%d (mín %d)  ·  slots libres=%d (mín %d)',
            $g['cola'], $g['cola_max'], $g['racha_seca'], $g['racha_min'], $g['slots_libres'], $g['slots_min']
        ));
        $this->line('  ' . $g['motivo']);

        $apply  = (bool) $this->option('apply');
        $forzar = (bool) $this->option('forzar');

        if (! $g['barre'] && ! $forzar) {
            if ($apply) {
                $this->warn('  → No se barre nada (gating cerrado). Usa --forzar si es a propósito.');

                return self::SUCCESS;
            }
            $this->line('  → Sigo en DRY-RUN sólo para reportar (no escribo nada, no toco el candado).');
        }

        if (! $apply) {
            return $this->reporteDryRun($barrido);
        }

        $sid = (string) $this->option('sid');
        $r   = $barrido->ciclo($sid, true);

        $this->line('');
        if (! ($r['tomo_candado'] ?? false)) {
            $this->warn('  ' . ($r['motivo'] ?? 'No se tomó el candado.'));

            return self::SUCCESS;
        }
        if (($r['modulo'] ?? null) === null) {
            $this->info('  Ningún módulo candidato para barrer. Candado tomado y liberado sin hacer nada.');

            return self::SUCCESS;
        }

        $this->line("<options=bold>MÓDULO BARRIDO:</> {$r['modulo']}");
        $this->line('  hallazgos=' . $r['hallazgos'] . '  creados=' . count($r['creados']));
        if ($r['creados']) {
            $filas = [];
            foreach ($r['creados'] as $c) {
                $filas[] = ['#' . $c['id'], $c['modulo'], $c['clase'], mb_substr($c['titulo'], 0, 70)];
            }
            $this->table(['Item', 'Módulo', 'Clase', 'Título'], $filas);
        } elseif ($r['hallazgos'] === 0) {
            $this->info('  Sin hallazgos en este módulo (honesto: no se inventó ruido).');
        } else {
            $this->info('  Todos los hallazgos ya existían (dedup por huella) — nada nuevo que crear.');
        }
        $this->line('  Candado liberado.');
        $this->line('');

        return self::SUCCESS;
    }

    /** DRY-RUN: no toma el candado, sólo muestra qué módulo/hallazgos vería el ciclo real. */
    private function reporteDryRun(BarridoService $barrido): int
    {
        $modulo = $barrido->elegirModulo();
        $this->line('');
        if ($modulo === null) {
            $this->info('  Ningún módulo candidato para barrer.');
            $this->line('');

            return self::SUCCESS;
        }

        $hallazgos = $barrido->explorar($modulo);
        $this->line("<options=bold>MÓDULO ELEGIDO:</> {$modulo}");
        if (! $hallazgos) {
            $this->info('  Sin hallazgos en este módulo.');
        } else {
            $filas = [];
            foreach ($hallazgos as $gap) {
                $filas[] = [$gap['tipo'], $gap['clase'], mb_substr($gap['titulo'], 0, 80)];
            }
            $this->table(['Detector', 'Clase', 'Título'], $filas);
        }
        $this->line('');
        $this->info('  DRY-RUN: no se tomó el candado ni se creó nada. Para ejecutarlo de verdad: circuito:barrido --apply');
        $this->line('');

        return self::SUCCESS;
    }
}
