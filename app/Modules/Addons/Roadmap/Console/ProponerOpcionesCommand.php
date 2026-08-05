<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * PROPONE opciones para items C en la bandeja (requiere_irving) que aún no las traen (#313).
 * El circuito PROPONE, Irving DECIDE: escribe SOLO el campo `opciones`; NUNCA toca opcion_elegida
 * ni el estado ni ejecuta nada. IDEMPOTENTE: salta los items que ya tienen opciones.
 *
 * Por defecto es DRY-RUN (muestra qué propondría, no escribe). Con --apply persiste. Opus cuesta →
 * tanda ACOTADA por --limit. Fuera de la ruta de ejecución del ejecutor (proponer ≠ ejecutar).
 *
 * #440 — --todos amplía el alcance de C-only a TODA la bandeja (B y C): la mayoría de requiere_irving
 * llegan por B (revisor/triaje), no solo C, y también merecen brief completo para que Irving decida
 * en una sola pasada. Excluye [BLOCKED-NEGOCIO] (bloqueado por decisión de negocio pendiente, no por
 * falta de brief). Sin --todos el comportamiento es IDÉNTICO al de antes (solo C).
 */
class ProponerOpcionesCommand extends Command
{
    protected $signature = 'circuito:proponer-opciones
        {--apply : escribe el brief (por defecto es dry-run: solo muestra)}
        {--limit=5 : máximo de items a briefear en esta pasada}
        {--id= : briefear solo para este item (ignora el filtro de cola)}
        {--rebrief : #432 BLOQUE 4 — re-briefea TODOS los C de la bandeja (aunque ya traigan brief) al modelo multi-pregunta}
        {--todos : #440 — amplía el alcance a TODA la bandeja requiere_irving (B y C), no solo C; excluye [BLOCKED-NEGOCIO]}';

    protected $description = 'Brief de decisión para la bandeja requiere_irving (multi-pregunta si el flag está ON; dry-run por defecto; Irving decide).';

    public function handle(RevisorService $revisor): int
    {
        $apply   = (bool) $this->option('apply');
        $limit   = max(1, (int) $this->option('limit'));
        $rebrief = (bool) $this->option('rebrief');
        $todos   = (bool) $this->option('todos');
        $multi   = RoadmapItem::multiPreguntaEnabled();

        // Selección: normal = C en la bandeja SIN brief (cSinOpciones, no pisa lo ya propuesto);
        // --rebrief (#432 BLOQUE 4) = TODOS los C de la bandeja, para re-briefearlos al modelo nuevo;
        // --todos (#440) = B y C de la bandeja (sin brief, salvo --rebrief también los re-briefea),
        // excluyendo lo ya rotulado [BLOCKED-NEGOCIO] (ese bloqueo no lo resuelve un brief).
        if ($todos) {
            $q = RoadmapItem::query()
                ->where('estado_aprobacion', 'requiere_irving')
                ->whereNotIn('status', ['done', 'cancelled'])
                ->whereIn('nivel_riesgo', ['B', 'C'])
                ->where('title', 'not like', '%BLOCKED-NEGOCIO%')
                ->where(function ($w) {
                    $w->whereNull('description')->orWhere('description', 'not like', '%BLOCKED-NEGOCIO%');
                });
            if ($multi && ! $rebrief) {
                $q->whereNull('preguntas');
            }
        } elseif ($rebrief) {
            $q = RoadmapItem::query()->where('estado_aprobacion', 'requiere_irving')->where('nivel_riesgo', 'C');
        } else {
            $q = RoadmapItem::query()->cSinOpciones();
        }
        if ($this->option('id')) {
            $q->where('id', (int) $this->option('id'));
        }
        $items = $q->orderByDesc('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->info('No hay items que briefear en esta pasada.');

            return self::SUCCESS;
        }

        $this->line(($apply ? 'APLICANDO' : 'DRY-RUN (no escribe)') . ' — ' . $items->count() . ' item(s) ' . ($todos ? 'B/C' : 'C')
            . ($multi ? ' [multi-pregunta]' : ' [opciones legacy]') . ($rebrief ? ' [re-brief]' : '') . ($todos ? ' [todos]' : '') . ':');
        $escritos = 0;

        foreach ($items as $item) {
            $this->newLine();
            $this->line("#{$item->id} — " . mb_strimwidth((string) $item->title, 0, 80, '…'));

            if ($multi) {
                $r     = $revisor->proponerPreguntas($item);
                $pregs = $r['preguntas'] ?? [];
                if (empty($pregs)) {
                    $this->warn('  · sin brief utilizable' . (isset($r['error']) ? ' (' . $r['error'] . ')' : '') . ' — se omite.');
                    continue;
                }
                foreach ($pregs as $pg) {
                    $this->line('  ▸ ' . ($pg['pregunta'] ?: '(decisión)') . ($pg['fase'] ? "  [{$pg['fase']}]" : '')
                        . (! empty($pg['requiere_irving']) ? '  [decide Irving]' : ''));
                    foreach ($pg['opciones'] as $op) {
                        // #507 sub-paso 1 — cada opción es un OBJETO {texto,recomendada,confianza,
                        // reversible}, ya no un string: concatenarla directo imprimía "Array".
                        $meta = [];
                        if (! empty($op['recomendada'])) {
                            $meta[] = 'RECOMENDADA';
                        }
                        if (! empty($op['confianza'])) {
                            $meta[] = 'confianza ' . $op['confianza'];
                        }
                        if (($op['reversible'] ?? null) !== null) {
                            $meta[] = $op['reversible'] ? 'reversible' : 'no reversible';
                        }
                        $this->line('      • ' . ($op['texto'] ?? '')
                            . ($meta ? '  [' . implode(' · ', $meta) . ']' : ''));
                    }
                }
                if (! $apply) {
                    continue;
                }
                $revisor->aplicarPreguntas($item, $pregs);
                $escritos++;
                Log::channel('roadmap_externo')->info('brief-completo', ['item' => $item->id, 'preguntas' => count($pregs), 'modelo' => $r['modelo'] ?? null]);
            } else {
                $r   = $revisor->proponerOpciones($item);
                $ops = $r['opciones'] ?? [];
                if (empty($ops)) {
                    $this->warn('  · sin propuesta utilizable' . (isset($r['error']) ? ' (' . $r['error'] . ')' : '') . ' — se omite.');
                    continue;
                }
                foreach ($ops as $op) {
                    $this->line('  • ' . $op);
                }
                if (! $apply) {
                    continue;
                }
                $fresh = RoadmapItem::find($item->id);
                if ($fresh && ! empty($fresh->opciones) && ! $rebrief) {
                    $this->warn('  · ya tiene opciones — se respeta, no se pisa.');
                    continue;
                }
                $fresh->opciones = $ops;
                $fresh->save();
                $escritos++;
                Log::channel('roadmap_externo')->info('proponer-opciones', ['item' => $fresh->id, 'n' => count($ops), 'modelo' => $r['modelo'] ?? null]);
            }
        }

        $this->newLine();
        $this->info($apply
            ? "Brief escrito: {$escritos} item(s). opcion_elegida intacta (la decide Irving en la Torre)."
            : 'Dry-run: nada escrito. Corre con --apply para persistir.');

        return self::SUCCESS;
    }
}
