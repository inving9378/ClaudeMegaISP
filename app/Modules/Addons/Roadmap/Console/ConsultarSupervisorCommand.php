<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\ThomasService;
use Illuminate\Console\Command;

/**
 * TORRE V2 — la terminal le PREGUNTA A THOMAS (no a Irving).
 *
 * Es la única vía por la que una terminal puede detenerse a consultar. Responde EN EL ACTO
 * (la política es determinista, no hay llamada a IA ni espera de un turno del loop), así que la
 * terminal no se queda bloqueada ni gasta su slot esperando.
 *
 * Exit code 0 = PROCEDE (sigue trabajando con la opción que Thomas te dio).
 * Exit code 1 = ESCALADO (detente y termina; el item ya está en la bandeja de Irving).
 * El prompt del ejecutor ramifica por ese código.
 *
 * Ejemplo:
 *   php artisan circuito:consultar 512 --sid=wt-3 \
 *     --pregunta="¿Renombro el campo o agrego uno nuevo y deprecio el viejo?" \
 *     --opcion="Agregar campo nuevo y deprecar el viejo|recomendada|reversible" \
 *     --opcion="Renombrar en sitio"
 */
class ConsultarSupervisorCommand extends Command
{
    protected $signature = 'circuito:consultar
        {id : ID del item sobre el que dudas}
        {--sid= : tu slot de terminal (wt-K)}
        {--pregunta= : la duda, en una frase}
        {--opcion=* : opción en formato "texto|recomendada|reversible" (los flags son opcionales)}
        {--json : responde en JSON en vez de texto}';

    protected $description = 'Consulta a Thomas (supervisor). Responde al instante: PROCEDE (0) o ESCALADO (1).';

    public function handle(ThomasService $thomas): int
    {
        $item = RoadmapItem::find($this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');

            return self::FAILURE;
        }

        $pregunta = trim((string) $this->option('pregunta'));
        if ($pregunta === '') {
            $this->error('Falta --pregunta: Thomas no adivina la duda.');

            return self::FAILURE;
        }

        $sid = trim((string) $this->option('sid')) ?: (string) ($item->worker_sid ?: 'terminal');

        $opciones = $this->parsearOpciones((array) $this->option('opcion'));

        $v = $thomas->resolverConsulta($item, $pregunta, $opciones, $sid);

        if ($this->option('json')) {
            $this->line(json_encode($v, JSON_UNESCAPED_UNICODE));
        } else {
            $this->line(strtoupper($v['decision']) . ': ' . $v['respuesta']);
            $this->line('Motivo: ' . $v['motivo']);
        }

        // El exit code es el contrato con el prompt del ejecutor.
        return $v['decision'] === 'procede' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * "texto|recomendada|reversible" → {texto, recomendada, reversible}. Los flags son posicionales
     * por NOMBRE, no por orden: da igual "…|reversible|recomendada".
     */
    private function parsearOpciones(array $crudas): array
    {
        $out = [];
        foreach ($crudas as $cruda) {
            $partes = array_map('trim', explode('|', (string) $cruda));
            $texto  = array_shift($partes);
            if ($texto === null || $texto === '') {
                continue;
            }
            $flags = array_map('mb_strtolower', $partes);
            $out[] = [
                'texto'       => $texto,
                'recomendada' => in_array('recomendada', $flags, true),
                'reversible'  => in_array('reversible', $flags, true),
            ];
        }

        return $out;
    }
}
