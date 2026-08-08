<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapReportService;
use Illuminate\Console\Command;

/**
 * #566 — Le da FOOTPRINT a los items "Sin clasificar".
 *
 * `modulo` no es una etiqueta cosmética: es el footprint con el que el scheduler serializa. Un
 * item con módulo desconocido podría tocar cualquier archivo, así que por diseño (#432 B2)
 * **corre SOLO y bloquea a las 6 terminales** mientras dure. Con la flota parada de a un item,
 * clasificar deja de ser higiene y pasa a ser throughput.
 *
 * Determinista (mapa de términos en `config/circuito.clasificador`), así que el mismo item da
 * siempre el mismo módulo y el resultado es auditable sin volver a correrlo. Lo que no matchea
 * se queda sin clasificar A PROPÓSITO: adivinar mal es peor que no adivinar, porque dos items
 * con footprint equivocado corren en paralelo y se pisan.
 *
 * Por default NO escribe (dry-run). Requiere `--apply` explícito.
 */
class ClasificarModuloCommand extends Command
{
    protected $signature = 'circuito:clasificar-modulo
        {--apply : escribe los cambios (sin esto solo muestra el plan)}
        {--id=* : limitar a estos ids}
        {--limit=200 : tope de items a revisar}';

    protected $description = 'Asigna footprint (modulo) a los items "Sin clasificar" para que dejen de serializar la flota.';

    public function handle(RoadmapReportService $reportes): int
    {
        $aplicar = (bool) $this->option('apply');

        $q = RoadmapItem::whereNull('archivado_at')
            ->whereNotIn('estado_aprobacion', ['completado', 'cancelado', 'rechazado'])
            ->where(fn ($x) => $x->whereNull('modulo')->orWhere('modulo', '')->orWhere('modulo', 'Sin clasificar'));

        if ($ids = array_filter((array) $this->option('id'))) {
            $q->whereIn('id', $ids);
        }

        $items = $q->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('No hay items sin clasificar.');

            return self::SUCCESS;
        }

        $clasificados = 0;
        $sinMatch     = [];

        foreach ($items as $item) {
            $modulo = $this->clasificar($item);

            if ($modulo === null) {
                $sinMatch[] = $item;
                continue;
            }

            $this->line(($aplicar ? '' : 'DRY ') . "#{$item->id} → «{$modulo}»  ·  "
                . mb_strimwidth(preg_replace('/\s+/', ' ', (string) $item->title), 0, 58, '…'));

            if ($aplicar) {
                $item->forceFill(['modulo' => $modulo])->save();
                $reportes->append(
                    $item,
                    'clasificador',
                    'nota',
                    "Footprint asignado: «{$modulo}» (antes sin clasificar).",
                    'Un item sin footprint corre solo y bloquea a las 6 terminales (#432 B2). '
                        . 'Clasificación determinista por config/circuito.clasificador.',
                    ['modulo' => $modulo]
                );
            }
            $clasificados++;
        }

        if ($sinMatch) {
            $this->newLine();
            $this->warn('Sin match (se quedan sin clasificar a propósito — adivinar mal los haría colisionar):');
            foreach ($sinMatch as $item) {
                $this->line("  #{$item->id}  " . mb_strimwidth(preg_replace('/\s+/', ' ', (string) $item->title), 0, 62, '…'));
            }
        }

        $this->newLine();
        $this->info(($aplicar ? '' : 'DRY — nada escrito. ')
            . "{$clasificados} clasificado(s), " . count($sinMatch) . ' sin match, de ' . $items->count() . ' revisado(s).');

        if (! $aplicar && $clasificados > 0) {
            $this->comment('Corre con --apply para escribirlo.');
        }

        return self::SUCCESS;
    }

    /**
     * Delega en el punto ÚNICO de clasificación (`ThomasService::clasificarModulo`), que comparte
     * con el alta desde la Torre. Devuelve null si nada matchea, y eso está bien: no clasificar es
     * mejor que clasificar mal.
     */
    private function clasificar(RoadmapItem $item): ?string
    {
        return app(\App\Modules\Addons\Roadmap\Services\ThomasService::class)
            ->clasificarModulo((string) $item->title . ' ' . (string) $item->description);
    }
}
