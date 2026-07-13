<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Console\Command;

/**
 * #427: pasada IDEMPOTENTE — rellena reporte_coloquial solo en items donde está vacío,
 * generando un resumen ~40 palabras desde title+description. No toca items que ya lo tienen.
 */
class BackfillReporteColoquialCommand extends Command
{
    protected $signature = 'roadmap:backfill-reporte-coloquial';

    protected $description = 'Genera reporte_coloquial (~40 palabras) para items del roadmap que lo tienen vacío.';

    public function handle(): int
    {
        $pendientes = RoadmapItem::where(function ($q) {
            $q->whereNull('reporte_coloquial')->orWhere('reporte_coloquial', '');
        })->get();

        foreach ($pendientes as $item) {
            $item->reporte_coloquial = RoadmapItem::generarReporteColoquial($item->title, $item->description);
            $item->saveQuietly();
        }

        $this->info("Backfill aplicado a {$pendientes->count()} item(s).");

        $vacios = RoadmapItem::where(function ($q) {
            $q->whereNull('reporte_coloquial')->orWhere('reporte_coloquial', '');
        })->count();

        $this->line("Vacíos restantes: {$vacios}");

        return $vacios === 0 ? self::SUCCESS : self::FAILURE;
    }
}
