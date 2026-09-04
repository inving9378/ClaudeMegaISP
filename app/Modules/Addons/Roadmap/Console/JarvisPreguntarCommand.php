<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\JarvisConocimientoService;
use Illuminate\Console\Command;

/**
 * ITEM #711 (Jarvis Parte 1) — punto de entrada manual para probar el índice vivo: le
 * pregunta por un módulo, tabla, comando circuito:* o item #N, y muestra la respuesta con
 * sus citas (archivo+línea / tabla+columna / medición). Si no sabe, lo dice explícito — nunca
 * rellena. Es el criterio de aceptación del item hecho comando.
 */
class JarvisPreguntarCommand extends Command
{
    protected $signature = 'circuito:jarvis-preguntar {pregunta* : la pregunta, sin comillas}';

    protected $description = 'Le pregunta al índice vivo de Jarvis (#711) y muestra la respuesta con sus citas.';

    public function handle(JarvisConocimientoService $svc): int
    {
        $pregunta = implode(' ', (array) $this->argument('pregunta'));
        $res = $svc->preguntar($pregunta);

        $this->line($res['respuesta']);

        if ($res['citas'] !== []) {
            $this->line('');
            $this->comment('Citas:');
            foreach ($res['citas'] as $c) {
                $this->line('  - ' . $this->formatearCita($c));
            }
        }

        $this->line('');
        $this->comment(
            'se_sabe=' . ($res['se_sabe'] ? 'true' : 'false')
            . ' | índice commit=' . substr((string) $res['indice']['commit'], 0, 12)
            . ' | HEAD=' . substr((string) $res['indice']['commit_actual'], 0, 12)
            . ' | desactualizado=' . ($res['indice']['desactualizado'] ? 'true' : 'false')
        );

        return self::SUCCESS;
    }

    private function formatearCita(array $c): string
    {
        return match ($c['tipo'] ?? null) {
            'archivo_linea' => "{$c['archivo']}:{$c['linea']}" . (isset($c['nota']) ? " ({$c['nota']})" : ''),
            'tabla_columna' => "tabla `{$c['tabla']}` — medido {$c['medido_at']}",
            'item_roadmap'  => "roadmap_items#{$c['id']} — medido {$c['medido_at']}",
            'medicion'      => ($c['detalle'] ?? 'medición') . ' — ' . ($c['medido_at'] ?? ''),
            default         => json_encode($c, JSON_UNESCAPED_UNICODE),
        };
    }
}
