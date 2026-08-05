<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\AutopilotService;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * BACKFILL de briefs de la bandeja (#507) — puebla `confianza`/`reversible` en los items cuyo brief
 * se escribió ANTES de que existiera el contrato estructurado (sub-paso 1). Sin esto el autopilot
 * no puede tocar la bandeja vieja: le falta el dato explícito y, por diseño, ante la ausencia manda
 * el item a Irving.
 *
 * REUSA la maquinaria existente (RevisorService::proponerPreguntas + aplicarPreguntas, lo mismo que
 * llaman `circuito:proponer-opciones` y `ProponerOpcionesJob`); lo que agrega es lo que un backfill
 * necesita y esos no tienen: ritmo (una llamada a la vez + pausa entre items y entre lotes), y los
 * candados de abajo.
 *
 * DOS CANDADOS:
 *  1. NO pisa un item donde Irving YA respondió alguna pregunta. `aplicarPreguntas` conserva las
 *     respuestas por ID de pregunta (q1, q2…), pero esos IDs son POSICIONALES: si el brief nuevo
 *     hace otras preguntas, la respuesta de la vieja q2 se pegaría a una pregunta distinta, y con
 *     una clave de opción que ya no existe. Un item así se salta entero y se reporta.
 *  2. Verifica el KILL SWITCH: este backfill se corre con el circuito en PAUSA a propósito, para
 *     que el autopilot (que se dispara al escribirse cada brief) no ejecute nada a mitad del
 *     proceso. Si el circuito NO está pausado, aborta salvo --sin-pausa explícito.
 */
class RebriefBandejaCommand extends Command
{
    protected $signature = 'circuito:rebrief-bandeja
        {--apply : escribe los briefs (por defecto dry-run: solo lista a quién tocaría)}
        {--limit=200 : máximo de items a procesar}
        {--sleep=3 : segundos entre items (ritmo, para no ráfagas contra la API)}
        {--lote=10 : items por lote}
        {--pausa-lote=20 : segundos de descanso entre lotes}
        {--sin-pausa : permite correr con el circuito ACTIVO (por defecto exige pausa)}
        {--solo-resumen : no rebriefea; solo evalúa la bandeja actual contra el autopilot}';

    protected $description = 'Backfill: regenera los briefs de la bandeja para poblar confianza/reversible (#507).';

    public function handle(RevisorService $revisor, AutopilotService $autopilot, RoadmapCircuitoService $circuito): int
    {
        $apply = (bool) $this->option('apply');

        if ($this->option('solo-resumen')) {
            $this->resumen($autopilot);

            return self::SUCCESS;
        }

        // Candado 2 — kill switch.
        if ($apply && ! $circuito->isPaused() && ! $this->option('sin-pausa')) {
            $this->error('El circuito NO está en pausa. Este backfill se corre con el kill switch activo '
                . 'para que el autopilot no ejecute nada a mitad de la regeneración. Pausa desde la Torre '
                . '(o usa --sin-pausa si de verdad lo quieres así).');

            return self::FAILURE;
        }

        $items = RoadmapItem::bandeja()->ordered()->limit((int) $this->option('limit'))->get();
        if ($items->isEmpty()) {
            $this->info('La bandeja está vacía: nada que rebriefear.');

            return self::SUCCESS;
        }

        $sleep     = max(0, (int) $this->option('sleep'));
        $lote      = max(1, (int) $this->option('lote'));
        $pausaLote = max(0, (int) $this->option('pausa-lote'));

        $this->line(($apply ? 'APLICANDO' : 'DRY-RUN (no escribe)') . ' — ' . $items->count()
            . ' item(s) de la bandeja · ritmo: ' . $sleep . 's entre items, ' . $pausaLote . 's cada ' . $lote);
        $this->line('circuito pausado: ' . ($circuito->isPaused() ? 'sí' : 'NO') . ' · autopilot: '
            . ($autopilot->enabled() ? 'encendido (no actuará mientras haya pausa)' : 'apagado'));
        $this->newLine();

        $ok = $fallo = $saltados = 0;
        $n = 0;

        foreach ($items as $item) {
            $n++;
            $etq = "#{$item->id} [" . ($item->nivel_riesgo ?: '—') . '] ' . mb_strimwidth((string) $item->title, 0, 58, '…');

            // Candado 1 — respuestas de Irving.
            $respondidas = collect($item->preguntasNormalizadas())
                ->filter(fn ($p) => ! empty($p['opcion_elegida']))->count();
            if ($respondidas > 0) {
                $this->line("  ⏭  {$etq} — ya respondiste {$respondidas} pregunta(s); NO se toca.");
                $saltados++;
                continue;
            }

            if (! $apply) {
                $this->line("  ·  {$etq}");
                continue;
            }

            try {
                $r     = $revisor->proponerPreguntas($item);
                $pregs = $r['preguntas'] ?? [];
                if (empty($pregs)) {
                    $this->warn("  ✗  {$etq} — sin brief utilizable" . (isset($r['error']) ? " ({$r['error']})" : ''));
                    $fallo++;
                } else {
                    // Mismo camino que el resto del circuito (dispara el autopilot, que en pausa no hace nada).
                    $revisor->aplicarPreguntas($item, $pregs, 'revisor:rebrief-backfill(#507)');
                    $conDatos = collect($pregs)->flatMap(fn ($p) => $p['opciones'])
                        ->filter(fn ($o) => ($o['confianza'] ?? null) !== null)->count();
                    $this->info("  ✓  {$etq} — " . count($pregs) . ' pregunta(s), ' . $conDatos . ' opción(es) con datos');
                    $ok++;
                    Log::channel('roadmap_externo')->info('rebrief-backfill', [
                        'item' => $item->id, 'preguntas' => count($pregs), 'modelo' => $r['modelo'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->warn("  ✗  {$etq} — excepción: " . mb_strimwidth($e->getMessage(), 0, 120, '…'));
                $fallo++;
            }

            // Ritmo: una llamada a la vez + respiro entre items y entre lotes.
            if ($n < $items->count()) {
                if ($n % $lote === 0) {
                    $this->line("     … lote de {$lote} hecho ({$n}/{$items->count()}), descansando {$pausaLote}s");
                    sleep($pausaLote);
                } elseif ($sleep > 0) {
                    sleep($sleep);
                }
            }
        }

        $this->newLine();
        $this->info("Backfill: {$ok} rebriefeados · {$saltados} saltados (con respuestas tuyas) · {$fallo} fallidos.");

        if ($apply) {
            $this->newLine();
            $this->resumen($autopilot);
        }

        return self::SUCCESS;
    }

    /**
     * CHECKPOINT: contra la bandeja actual, cuántos calificarían al autopilot y cuántos siguen
     * siendo tuyos, con el desglose por nivel y el motivo de los que no. Evalúa en seco (ignora la
     * pausa) — no escribe ni ejecuta nada.
     */
    private function resumen(AutopilotService $autopilot): void
    {
        $items = RoadmapItem::bandeja()->ordered()->get();

        $califican = ['A' => 0, 'B' => 0, 'C' => 0, '—' => 0];
        $quedan    = ['A' => 0, 'B' => 0, 'C' => 0, '—' => 0];
        $motivos   = [];
        $listaOk   = [];

        foreach ($items as $i) {
            $nv = $i->nivel_riesgo ?: '—';
            $r  = $autopilot->evaluar($i, true);
            if ($r['auto']) {
                $califican[$nv]++;
                $listaOk[] = "#{$i->id} [{$nv}] " . mb_strimwidth((string) $i->title, 0, 52, '…')
                    . " · confianza {$r['confianza']}";
            } else {
                $quedan[$nv]++;
                $motivos[$r['motivo']] = ($motivos[$r['motivo']] ?? 0) + 1;
            }
        }

        $totalOk = array_sum($califican);
        $this->line(str_repeat('─', 74));
        $this->info("CHECKPOINT — bandeja: {$items->count()} items");
        $this->line("  Calificarían al AUTOPILOT: {$totalOk}   (A:{$califican['A']} · B:{$califican['B']} · C:{$califican['C']} · sin nivel:{$califican['—']})");
        $this->line('  Se quedan para IRVING:    ' . array_sum($quedan)
            . "   (A:{$quedan['A']} · B:{$quedan['B']} · C:{$quedan['C']} · sin nivel:{$quedan['—']})");

        if ($listaOk) {
            $this->newLine();
            $this->line('  Los que el autopilot tomaría al reanudar:');
            foreach ($listaOk as $l) {
                $this->line('    ' . $l);
            }
        }
        if ($motivos) {
            arsort($motivos);
            $this->newLine();
            $this->line('  Por qué se quedan contigo:');
            foreach ($motivos as $m => $c) {
                $this->line(sprintf('    %-26s %d', $m, $c));
            }
        }
        $this->line(str_repeat('─', 74));
    }
}
