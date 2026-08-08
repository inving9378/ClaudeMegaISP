<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\AuditorService;
use Illuminate\Console\Command;

/**
 * MOTOR DE AUDITORÍA CONTINUA (#559) — el generador de trabajo del circuito.
 *
 * Escanea módulo por módulo, detecta lo que falta y crea los items-hijo que lo cierran, para que la
 * cola que reparte `circuito:scheduler` no se vacíe. No reparte ni aprueba: sólo genera.
 *
 * DRY-RUN POR DEFAULT: sin `--apply` NO escribe nada, sólo reporta qué crearía y por qué. Crear
 * items es la acción irreversible-en-la-práctica de este motor (ensucia la Hoja de Ruta y consume
 * terminales), así que el default es el lado seguro.
 *
 *   php artisan circuito:auditor                    # dry-run del ciclo completo
 *   php artisan circuito:auditor --modulo=Tickets   # sólo un módulo
 *   php artisan circuito:auditor --apply --cap=10   # en vivo, con tope
 *   php artisan circuito:auditor --dod              # qué módulos están en su DoD de Fase 1
 */
class AuditorCommand extends Command
{
    protected $signature = 'circuito:auditor
        {--apply : Crea los items. Sin esto = DRY-RUN (no escribe nada)}
        {--cap= : Máximo de items nuevos en este ciclo (default: circuito.auditor.cap_por_ciclo)}
        {--modulo= : Audita SÓLO este módulo (nombre del directorio, ej. Tickets)}
        {--forzar : Ignora el umbral de cola y el intervalo mínimo}
        {--dod : Sólo reporta qué módulos están en su DoD de Fase 1, sin crear nada}
        {--detalle : Muestra el detalle completo de cada gap candidato}';

    protected $description = 'Motor de Auditoría Continua (#559): detecta gaps por módulo y genera los items que los cierran.';

    public function handle(AuditorService $auditor): int
    {
        if ($this->option('dod')) {
            return $this->reporteDoD($auditor);
        }

        $apply = (bool) $this->option('apply');
        $cap   = $this->option('cap') !== null ? max(0, (int) $this->option('cap')) : null;

        // ── Gating ────────────────────────────────────────────────────────────────────────────
        $g = $auditor->debeCorrer((bool) $this->option('forzar'));
        $this->line('');
        $this->line('<options=bold>MOTOR DE AUDITORÍA CONTINUA (#559)</>');
        $this->line(sprintf('  cola reclamable=%d  ·  umbral=%d  ·  terminales libres=%d', $g['cola'], $g['umbral'], $g['slots_libres']));
        $this->line('  ' . $g['motivo']);

        if (! $g['corre']) {
            // Con --modulo o --dry seguimos escaneando para poder AUDITAR el motor sin encenderlo;
            // lo que nunca pasa es crear items cuando el gating dijo que no.
            if ($apply) {
                $this->warn('  → No se crea nada (gating cerrado). Usa --forzar si es a propósito.');

                return self::SUCCESS;
            }
            $this->line('  → Sigo en DRY-RUN sólo para reportar (no escribo nada).');
        }

        // ── Ciclo ─────────────────────────────────────────────────────────────────────────────
        $aplicaDeVerdad = $apply && $g['corre'];
        $r = $auditor->ciclo($aplicaDeVerdad, $cap, $this->option('modulo') ?: null);

        // ── Reporte por módulo ────────────────────────────────────────────────────────────────
        $this->line('');
        $this->line('<options=bold>ESCANEO POR MÓDULO</>');
        $filas = [];
        foreach ($r['por_modulo'] as $modulo => $s) {
            if ($s['detectados'] === 0 && $s['nuevos'] === 0) {
                continue;
            }
            $filas[] = [
                $modulo,
                $s['detectados'],
                $s['ya_existen'],
                $s['nuevos'],
                $s['mecanicos'],
                $s['producto'],
                $s['en_dod'] ? 'sí' : '—',
            ];
        }
        if ($filas) {
            $this->table(['Módulo', 'Gaps', 'Ya existen', 'Nuevos', 'Mecánicos', 'Producto', 'DoD F1'], $filas);
        } else {
            $this->info('  Ningún módulo tiene gaps nuevos. El motor se queda quieto (eso está bien).');
        }

        $enDoD = count(array_filter($r['por_modulo'], fn ($s) => $s['en_dod']));
        $this->line(sprintf('  Módulos auditados: %d  ·  en DoD de Fase 1: %d', count($r['por_modulo']), $enDoD));

        // ── Candidatos de este ciclo ──────────────────────────────────────────────────────────
        $this->line('');
        $this->line('<options=bold>' . ($aplicaDeVerdad ? 'ITEMS CREADOS' : 'ITEMS QUE SE CREARÍAN') . '</> (cap ' . $r['cap'] . ')');

        if (! $r['elegidos']) {
            $this->info('  Ninguno.');
        } elseif ($aplicaDeVerdad) {
            $filas = [];
            foreach ($r['creados'] as $c) {
                $filas[] = ['#' . $c['id'], $c['modulo'], $c['clase'], $c['tipo'], mb_substr($c['titulo'], 0, 72)];
            }
            $this->table(['Item', 'Módulo (footprint)', 'Clase', 'Detector', 'Título'], $filas);
            $this->info(sprintf('  %d item(s) creados de %d candidatos.', count($r['creados']), count($r['elegidos'])));
        } else {
            $filas = [];
            foreach ($r['elegidos'] as $gap) {
                $filas[] = [
                    $gap['modulo'],
                    $gap['clase'] === 'producto' ? 'PRODUCTO → Irving' : 'mecánico → cola',
                    $gap['tipo'],
                    mb_substr($gap['titulo'], 0, 70),
                ];
            }
            $this->table(['Módulo (footprint)', 'Destino', 'Detector', 'Título'], $filas);

            if ($this->option('detalle')) {
                foreach ($r['elegidos'] as $gap) {
                    $this->line('');
                    $this->line("<options=bold>── {$gap['titulo']}</>");
                    $this->line("   módulo={$gap['modulo']}  clase={$gap['clase']}  tipo={$gap['tipo']}"
                        . (isset($gap['frontera']) ? "  frontera={$gap['frontera']}" : ''));
                    foreach (explode("\n", $gap['detalle']) as $l) {
                        $this->line('   ' . $l);
                    }
                    if (! empty($gap['pregunta'])) {
                        $this->line('   PREGUNTA A IRVING: ' . $gap['pregunta']);
                    }
                }
            }

            $mec  = count(array_filter($r['elegidos'], fn ($x) => $x['clase'] === 'mecanico'));
            $prod = count($r['elegidos']) - $mec;
            $this->line('');
            $this->info("  DRY-RUN: se crearían {$mec} item(s) mecánico(s) a la cola y {$prod} a la bandeja de Irving.");
            $this->line('  Para ejecutarlo de verdad: <options=bold>php artisan circuito:auditor --apply</>');
        }

        $this->line('');

        return self::SUCCESS;
    }

    /** Reporte de DoD: qué módulos ya no tienen gaps mecánicos detectables. */
    private function reporteDoD(AuditorService $auditor): int
    {
        $this->line('');
        $this->line('<options=bold>DoD de Fase 1 por módulo</> (sin gaps mecánicos detectables)');
        $filas = [];
        foreach ($auditor->modulosAAuditar() as $m) {
            $gaps = $auditor->detectarGaps($m);
            $mec  = count(array_filter($gaps, fn ($g) => $g['clase'] === 'mecanico'));
            $prod = count($gaps) - $mec;
            $filas[] = [$m, $auditor->rutaModulo($m) ? 'sí' : 'no', $mec, $prod, $mec === 0 ? '✔ DoD F1' : '—'];
        }
        $this->table(['Módulo', 'En disco', 'Gaps mecánicos', 'Gaps de producto', 'Estado'], $filas);
        $this->line('');

        return self::SUCCESS;
    }
}
