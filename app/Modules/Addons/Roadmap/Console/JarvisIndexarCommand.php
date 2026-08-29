<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\JarvisIndiceService;
use Illuminate\Console\Command;

/**
 * ITEM #711 (Jarvis Parte 1) — reconstruye el índice vivo leyendo el sistema real (módulos,
 * migraciones, rutas, comandos circuito:*, schedule de Kernel.php) y lo persiste marcado con
 * el commit actual. Se dispara solo tras cada merge a main (MergeRunner::performMerge) y
 * también sirve para correrlo a mano tras un `migrate`/instalación de módulo fuera del flujo.
 */
class JarvisIndexarCommand extends Command
{
    protected $signature = 'circuito:jarvis-indexar';

    protected $description = 'Reconstruye el índice vivo de Jarvis (módulos/rutas/comandos/schedule) desde el sistema real.';

    public function handle(JarvisIndiceService $svc): int
    {
        $indice = $svc->construirYGuardar();

        $this->info(sprintf(
            'Índice reconstruido: %d módulo(s), %d comando(s) circuito:*, %d entrada(s) de schedule. commit=%s (%dms).',
            count($indice['modulos']),
            count($indice['comandos_circuito']),
            count($indice['schedule_kernel']),
            substr((string) $indice['commit'], 0, 12),
            $indice['duracion_ms']
        ));

        return self::SUCCESS;
    }
}
