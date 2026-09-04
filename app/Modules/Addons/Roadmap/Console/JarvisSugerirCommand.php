<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\JarvisSugerenciasService;
use Illuminate\Console\Command;

/**
 * ITEM #805 (Jarvis Parte 3a, sub-item de #713) — punto de entrada SOLO LECTURA: imprime hasta
 * 3 candidatos a sugerencia (patrones que se repiten / deuda que empieza a doler / capacidades
 * casi existentes), cada uno con su cita verificable. NUNCA crea items en la Hoja de Ruta —
 * eso sigue siendo exclusivo de AuditorService (Parte 2, #712, `auditor_fingerprint`).
 */
class JarvisSugerirCommand extends Command
{
    protected $signature = 'circuito:jarvis-sugerir';

    protected $description = 'Detecta hasta 3 candidatos a sugerencia con cita verificable, solo lectura (Jarvis Parte 3a, #805).';

    public function handle(JarvisSugerenciasService $svc): int
    {
        $res = $svc->detectar();

        if ($res['candidatos'] === []) {
            $this->info('Sin candidatos detectables ahora mismo (ningún detector encontró evidencia citable).');

            return self::SUCCESS;
        }

        foreach ($res['candidatos'] as $i => $c) {
            $this->line('');
            $this->comment('[' . ($i + 1) . '] ' . $c['categoria']);
            $this->line($c['texto']);
            foreach ($c['citas'] as $cita) {
                $this->line('  - ' . $this->formatearCita($cita));
            }
        }

        $this->line('');
        $this->comment(
            'commit=' . substr((string) $res['commit'], 0, 12)
            . ' | generado_at=' . $res['generado_at']
            . ' | candidatos=' . count($res['candidatos'])
        );

        return self::SUCCESS;
    }

    private function formatearCita(array $c): string
    {
        return match ($c['tipo'] ?? null) {
            'archivo_linea' => "{$c['archivo']}:{$c['linea']}" . (isset($c['nota']) ? " ({$c['nota']})" : ''),
            'item_roadmap'  => "roadmap_items#{$c['id']}" . (isset($c['columna']) ? " ({$c['columna']})" : ''),
            default         => json_encode($c, JSON_UNESCAPED_UNICODE),
        };
    }
}
