<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ResumenNaturalService;
use Illuminate\Console\Command;

/**
 * #652 — Rellena `resumen_natural` en los items que no lo tienen.
 *
 * Prioriza la BANDEJA: es la pantalla que Irving lee todos los días y la única donde el
 * texto técnico le cuesta tiempo de verdad. El resto del roadmap puede esperar.
 */
class ResumenNaturalCommand extends Command
{
    protected $signature = 'circuito:resumen-natural
        {--limit=20 : máximo de items a procesar en esta corrida}
        {--id= : un solo item, por id}
        {--todos : no limitarse a la bandeja; incluye cualquier item sin resumen}
        {--rehacer : re-generar aunque ya tenga resumen}
        {--dry : muestra lo que generaría, sin guardar}
        {--preguntas : traduce también las preguntas del brief (pieza 2)}';

    protected $description = 'Traduce a lenguaje llano los items del roadmap (bandeja primero).';

    public function handle(ResumenNaturalService $svc): int
    {
        if (! $svc->enabled()) {
            $this->warn('Servicio apagado (circuito.resumen_natural.enabled).');

            return self::SUCCESS;
        }

        $q = RoadmapItem::query();

        if ($this->option('id')) {
            $q->whereKey((int) $this->option('id'));
        } else {
            if (! $this->option('todos')) {
                $q->bandeja();
            }
            if (! $this->option('rehacer')) {
                // Con --preguntas el trabajo pendiente es OTRO: los items cuyo brief aún no se
                // tradujo. Filtrar por `resumen_natural` vacío no seleccionaría ninguno una vez
                // hecho el backfill de la pieza 1, y el comando no haría nada sin decir por qué.
                if ($this->option('preguntas')) {
                    $q->whereNotNull('preguntas')
                      ->where(fn ($w) => $w->whereNull('preguntas_natural')->orWhere('preguntas_natural', ''));
                } else {
                    $q->where(fn ($w) => $w->whereNull('resumen_natural')->orWhere('resumen_natural', ''));
                }
            }
            // Los más nuevos primero: son los que Irving tiene delante ahora mismo.
            $q->orderByDesc('id');
        }

        $items = $q->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('Nada que traducir.');

            return self::SUCCESS;
        }

        $dry  = (bool) $this->option('dry');
        $ok   = 0;
        $fail = 0;

        foreach ($items as $item) {
            // Si ya tiene resumen y no se pidió rehacerlo, no se vuelve a pagar esa llamada:
            // en modo --preguntas lo que falta es el brief, no el resumen.
            $yaTiene = trim((string) $item->resumen_natural) !== '';
            $r = ($yaTiene && ! $this->option('rehacer')) ? $item->resumen_natural : $svc->generar($item);

            if ($r === null) {
                $fail++;
                $this->line("  <fg=yellow>#{$item->id}</> no se pudo traducir (queda con su título técnico).");
                continue;
            }

            if (! $dry && ! ($yaTiene && ! $this->option('rehacer'))) {
                $item->resumen_natural    = $r;
                $item->resumen_natural_at = now();
                $item->save();
            }

            $ok++;
            $this->line(sprintf('  %s#%d</> %s', $dry ? '<fg=cyan>DRY ' : '<fg=green>', $item->id, mb_strimwidth($r, 0, 110, '…')));

            // Pieza 2: el brief en llano. Va DESPUÉS del resumen y sólo si se pide, porque es
            // otra llamada por item y no todos los items tienen brief.
            if ($this->option('preguntas') && ! $dry) {
                $this->line($svc->generarYGuardarPreguntas($item)
                    ? '      brief traducido ✓'
                    : '      <fg=yellow>brief NO traducido</> (sin preguntas, o el casador rechazó la respuesta)');
            }
        }

        $this->newLine();
        $this->info(($dry ? 'DRY: ' : '') . "{$ok} traducidos · {$fail} sin traducir · de {$items->count()} evaluados.");

        $faltan = RoadmapItem::bandeja()
            ->where(fn ($w) => $w->whereNull('resumen_natural')->orWhere('resumen_natural', ''))
            ->count();
        $this->line("  Quedan {$faltan} en la bandeja sin resumen.");

        return self::SUCCESS;
    }
}
