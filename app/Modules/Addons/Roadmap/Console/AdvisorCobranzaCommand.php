<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\Advisors\CobranzaAdvisorService;
use Illuminate\Console\Command;

/**
 * Item #344 — piloto mínimo del "consejo asesor": UN rol (Cobranza), corrida MANUAL bajo
 * demanda (sin agenda/scheduler todavía — así lo pidió Irving en el brief de decisión).
 * Solo lectura sobre agregados de facturación; deja de 1 a 3 propuestas en la Hoja de Ruta
 * como items nivel C (requiere_irving). NADA se auto-ejecuta.
 */
class AdvisorCobranzaCommand extends Command
{
    protected $signature = 'advisor:cobranza';

    protected $description = 'Piloto del consejo asesor (#344): el asesor de Cobranza analiza agregados read-only y propone hasta 3 items al roadmap (nivel C, requiere_irving).';

    public function handle(CobranzaAdvisorService $service): int
    {
        $this->info('Asesor de Cobranza: analizando agregados de facturación (solo lectura)...');
        $r = $service->run();

        if (! $r['ok']) {
            $this->warn('No se generaron propuestas: ' . ($r['error'] ?? 'sin detalle'));

            return self::SUCCESS;
        }

        $this->info('Modelo: ' . $r['modelo']);
        foreach ($r['creados'] as $item) {
            $this->line("Propuesta creada → item #{$item->id}: {$item->title}");
        }
        $this->info(count($r['creados']) . ' propuesta(s) creada(s) en requiere_irving.');

        return self::SUCCESS;
    }
}
