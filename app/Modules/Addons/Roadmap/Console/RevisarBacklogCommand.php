<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService;
use Illuminate\Console\Command;

/**
 * #338 — Pasada del REVISOR sobre el backlog de B atascados (nivel B, pendiente_revision).
 * Corre el revisor adversarial sobre una tanda ACOTADA y reporta sus veredictos. Con --apply
 * fija el estado (aprobado_revisor | requiere_irving); sin --apply solo reporta (dry-run).
 *
 * CONSERVADOR: respeta el kill switch (#342, en pausa nunca aplica) y los candados #341
 * (tomablePorCircuito excluye en_progreso / en_desarrollo_humano). Todo auditado y reversible.
 */
class RevisarBacklogCommand extends Command
{
    protected $signature = 'circuito:revisar-backlog
        {--limit=12 : Máximo de items B a revisar en esta pasada}
        {--apply : Fija el estado (aprobado_revisor|requiere_irving). Sin esto = dry-run}
        {--modulo= : Acota a un módulo (like)}';

    protected $description = 'Corre el revisor adversarial (#338) sobre el backlog de B atascados y reporta/aplica.';

    public function handle(RevisorService $revisor, RoadmapCircuitoService $circuito): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $apply = (bool) $this->option('apply');
        $pausa = $circuito->isPaused();

        if ($apply && $pausa) {
            $this->warn('Circuito en PAUSA (kill switch): --apply IGNORADO, solo se reporta (no se autoriza en pausa).');
            $apply = false;
        }

        $q = RoadmapItem::where('nivel_riesgo', 'B')
            ->where('estado_aprobacion', 'pendiente_revision')
            ->where('status', 'pending')
            ->tomablePorCircuito();
        if ($this->option('modulo')) {
            $q->where('modulo', 'like', '%' . $this->option('modulo') . '%');
        }
        $items = $q->ordered()->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->info('No hay B atascados (nivel B, pendiente_revision) en alcance. Nada que revisar en la rama B.');
        } else {
            $this->info(($apply ? 'APLICANDO' : 'DRY-RUN (solo reporte)') . " — revisando {$items->count()} item(s) B con el revisor adversarial…");
            $rows = [];
            $nAuto = 0;
            $nEsc = 0;
            foreach ($items as $item) {
                $v = $revisor->revisar($item, $item->comentarios_claude);
                $aplicado = 'no';
                if ($apply) {
                    $revisor->aplicarVeredicto($item, $v, 'revisor:backlog');
                    $aplicado = ($v['veredicto'] === 'autoriza' && ! $pausa) ? 'aprobado_revisor' : 'requiere_irving';
                }
                $v['veredicto'] === 'autoriza' ? $nAuto++ : $nEsc++;
                $rows[] = [
                    $item->id,
                    mb_strimwidth($item->title, 0, 46, '…'),
                    $v['veredicto'],
                    $v['en_alcance'] ? 'sí' : 'no',
                    $v['categoria_escalada'] ?? '-',
                    $v['confianza'] ?? '-',
                    $aplicado,
                    mb_strimwidth((string) ($v['razon'] ?? ''), 0, 60, '…'),
                ];
            }

            $this->table(['#', 'título', 'veredicto', 'alcance', 'categoría', 'conf', 'aplicado', 'razón'], $rows);
            $this->line('');
            $this->info("Resumen B: {$nAuto} autoriza · {$nEsc} escala" . ($apply ? ' (aplicados)' : ' (dry-run, sin cambios)') . '.');
        }

        // #419 — Rama de TRIAJE de nivel para items con nivel_riesgo NULL (punto ciego: ni el
        // ejecutor —pide A— ni la rama B de arriba —pide B— los toman). Corre SIEMPRE (aunque la
        // rama B esté vacía, que es justo el caso que destapa la fuga).
        $this->triarNulls($revisor, $apply, $limit);

        $this->line('Auditoría de la rama B en la tabla `circuito_revisiones`.');

        return self::SUCCESS;
    }

    /**
     * #419 — Recoge los pendiente_revision con nivel_riesgo NULL y les ASIGNA nivel de forma
     * DETERMINISTA (nunca ejecuta, nunca asigna A): C→requiere_irving si toca frontera dura;
     * B→sigue pendiente_revision (lo evalúa la próxima pasada B). Respeta el candado humano
     * (tomablePorCircuito excluye en_desarrollo_humano=1) y la pausa (vía $apply ya bajado en handle).
     */
    private function triarNulls(RevisorService $revisor, bool $apply, int $limit): void
    {
        $q = RoadmapItem::whereNull('nivel_riesgo')
            ->where('estado_aprobacion', 'pendiente_revision')
            ->where('status', 'pending')
            ->tomablePorCircuito();
        if ($this->option('modulo')) {
            $q->where('modulo', 'like', '%' . $this->option('modulo') . '%');
        }
        $items = $q->ordered()->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->info('Triaje null (#419): no hay items nivel_riesgo=NULL pendientes. Nada que triar.');

            return;
        }

        $this->info(($apply ? 'APLICANDO' : 'DRY-RUN (solo reporte)') . " — triando {$items->count()} item(s) nivel NULL (#419)…");
        $rows = [];
        $nB = 0;
        $nC = 0;
        foreach ($items as $item) {
            $t = $revisor->triarNivelNull($item);
            if ($apply) {
                $revisor->aplicarTriajeNull($item, $t);
            }
            $t['nivel'] === 'C' ? $nC++ : $nB++;
            $rows[] = [
                $item->id,
                mb_strimwidth($item->title, 0, 46, '…'),
                $t['nivel'],
                $t['estado'],
                $t['match'] ?? '-',
                $apply ? 'sí' : 'no',
                mb_strimwidth((string) ($t['motivo'] ?? ''), 0, 52, '…'),
            ];
        }

        $this->table(['#', 'título', 'nivel→', 'estado→', 'match', 'aplicado', 'motivo'], $rows);
        $this->info("Resumen triaje NULL: {$nB} → B · {$nC} → C" . ($apply ? ' (aplicados)' : ' (dry-run, sin cambios)') . '.');
    }
}
