<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Models\RoadmapItemReport;
use App\Modules\Addons\Roadmap\Services\RoadmapReportService;
use Illuminate\Console\Command;

/**
 * TORRE V2 — la terminal deja constancia en el historial del item.
 *
 * Reemplaza el `$i->comentarios_claude .= "\nEJECUTADO: …"` que el prompt del ejecutor mandaba
 * hacer por tinker: con seis terminales sobre la misma columna, dos escrituras a la vez se pisan
 * y se pierde el rastro de quién decidió qué. Aquí cada reporte es una fila propia e inmutable.
 *
 * Se usa sobre todo para dejar asentada una decisión tomada por la REGLA DE ORO (avanzar sobre la
 * opción recomendada sin consultar), que es justo lo que Irving revisa después en vez de antes.
 */
class ReportarItemCommand extends Command
{
    protected $signature = 'circuito:reportar
        {id : ID del item}
        {--sid= : tu slot de terminal (wt-K)}
        {--tipo=avance : avance|decision|verificacion|escalacion|cierre|nota}
        {--resumen= : una línea, lo que se ve en la Torre}
        {--cuerpo= : detalle opcional}';

    protected $description = 'Agrega un reporte al historial del item (append-only, no pisa los anteriores).';

    public function handle(RoadmapReportService $reportes): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');

            return self::FAILURE;
        }

        $resumen = trim((string) $this->option('resumen'));
        if ($resumen === '') {
            $this->error('Falta --resumen.');

            return self::FAILURE;
        }

        $tipo = (string) $this->option('tipo');
        if (! in_array($tipo, RoadmapItemReport::TIPOS, true)) {
            $this->error('Tipo inválido. Válidos: ' . implode(', ', RoadmapItemReport::TIPOS));

            return self::FAILURE;
        }

        $autor = trim((string) $this->option('sid')) ?: (string) ($item->worker_sid ?: 'terminal');
        $r = $reportes->append($item, $autor, $tipo, $resumen, $this->option('cuerpo') ?: null);

        $this->info("Reporte #{$r->id} agregado al item #{$item->id} ({$tipo}).");

        return self::SUCCESS;
    }
}
