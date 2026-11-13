<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Services\SincronizadorAsterisk;
use Illuminate\Console\Command;

/**
 * Lo mismo que el botón «Sincronizar con Asterisk». Sin --aplicar solo lista.
 * Para la parte de troncales escribe en /etc/asterisk/megaisp.d: córrelo como el usuario
 * del servidor web (sudo -u www-data php artisan voip:sincronizar --aplicar).
 */
class SincronizarAsteriskCommand extends Command
{
    protected $signature = 'voip:sincronizar {--aplicar : Hacer los cambios (sin esto solo los lista)}';

    protected $description = 'Compara MegaISP con Asterisk (extensiones, troncales, colas, plan de marcado) y corrige lo desalineado.';

    public function handle(SincronizadorAsterisk $sincronizador): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $r = $sincronizador->revisar($aplicar);

        $this->line($aplicar ? 'Hecho:' : 'Se haría (corre con --aplicar para hacerlo):');
        foreach ($r['tareas'] as $t) {
            $this->line('  • ' . $t);
        }
        if (! $r['tareas']) {
            $this->line('  (nada)');
        }
        if ($r['pendientes']) {
            $this->warn('Requiere a una persona:');
            foreach ($r['pendientes'] as $p) {
                $this->line('  • ' . $p);
            }
        }
        if ($r['fallas']) {
            $this->error('Fallaron:');
            foreach ($r['fallas'] as $f) {
                $this->line('  • ' . $f);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
