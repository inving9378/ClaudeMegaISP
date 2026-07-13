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
 */
class ProponerOpcionesCommand extends Command
{
    protected $signature = 'circuito:proponer-opciones
        {--apply : escribe las opciones (por defecto es dry-run: solo muestra)}
        {--limit=5 : máximo de items C a proponer en esta pasada}
        {--id= : proponer solo para este item (ignora el filtro de cola)}';

    protected $description = 'Propone 2-3 opciones para items C sin opciones (dry-run por defecto; Irving decide).';

    public function handle(RevisorService $revisor): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(1, (int) $this->option('limit'));

        $q = RoadmapItem::query()
            ->where('nivel_riesgo', 'C')
            ->where('estado_aprobacion', 'requiere_irving')
            ->whereNotIn('status', ['done', 'cancelled'])
            // IDEMPOTENCIA: solo los que NO traen opciones (no pisa lo ya propuesto/elegido).
            ->where(function ($w) {
                $w->whereNull('opciones')->orWhere('opciones', '[]')->orWhere('opciones', '');
            });

        if ($this->option('id')) {
            $q->where('id', (int) $this->option('id'));
        }

        $items = $q->orderByDesc('id')->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->info('No hay items C en la bandeja sin opciones. Nada que proponer.');

            return self::SUCCESS;
        }

        $this->line(($apply ? 'APLICANDO' : 'DRY-RUN (no escribe)') . ' — ' . $items->count() . ' item(s) C:');
        $escritos = 0;

        foreach ($items as $item) {
            $r = $revisor->proponerOpciones($item);
            $ops = $r['opciones'] ?? [];

            $this->newLine();
            $this->line("#{$item->id} — " . mb_strimwidth((string) $item->title, 0, 80, '…') . '  (' . ($r['modelo'] ?? '?') . ')');

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

            // GUARDA IDEMPOTENTE: re-verifica que sigan vacías antes de escribir (no pisa opcion_elegida ni estado).
            $fresh = RoadmapItem::find($item->id);
            if ($fresh && ! empty($fresh->opciones)) {
                $this->warn('  · ya tiene opciones (otra pasada) — se respeta, no se pisa.');
                continue;
            }
            $fresh->opciones = $ops;   // SOLO el campo opciones
            $fresh->save();
            $escritos++;
            Log::channel('roadmap_externo')->info('proponer-opciones', ['item' => $fresh->id, 'n' => count($ops), 'modelo' => $r['modelo'] ?? null]);
        }

        $this->newLine();
        if ($apply) {
            $this->info("Opciones escritas: {$escritos} item(s). opcion_elegida intacta (la pone Irving en la Torre).");
        } else {
            $this->info('Dry-run: nada escrito. Corre con --apply para persistir.');
        }

        return self::SUCCESS;
    }
}
