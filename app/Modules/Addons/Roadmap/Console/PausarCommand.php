<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\FrenoCircuito;
use Illuminate\Console\Command;

/**
 * FRENO DE MANO desde consola (#170) — funciona CON LA BASE CAÍDA.
 *
 * No toca `settings` ni Eloquent: escribe el centinela y ya. Ése es el punto entero del item — el
 * freno tenía que dejar de depender de que MySQL conteste.
 *
 * NO viola el candado #342 (que el ejecutor no pueda soltarse el freno a sí mismo): #342 protege
 * `setPaused()`, que es el camino que SUELTA y APRIETA desde la Torre. Aquí sólo se APRIETA, y
 * apretar el freno nunca ha necesitado protección: es la dirección segura. Soltarlo vive en
 * `circuito:reanudar`, con sus propias condiciones.
 */
class PausarCommand extends Command
{
    protected $signature = 'circuito:pausar
        {--motivo= : Por qué se frena (queda escrito en el centinela)}
        {--quien= : Quién frena (default: el usuario del sistema)}';

    protected $description = 'Pone el freno de mano del circuito escribiendo el centinela en archivo (#170).';

    public function handle(): int
    {
        $motivo = trim((string) $this->option('motivo'));
        if ($motivo === '') {
            // Se EXIGE motivo: un freno sin motivo es el que nadie se atreve a soltar tres días
            // después porque no sabe qué se estaba conteniendo.
            $this->error('Falta --motivo. Un freno sin motivo escrito es un freno que nadie se atreve a soltar.');

            return self::FAILURE;
        }

        $quien = trim((string) $this->option('quien')) ?: ('consola:' . (get_current_user() ?: 'desconocido'));

        if (FrenoCircuito::activo()) {
            $d = FrenoCircuito::detalle();
            $this->warn('El freno YA estaba puesto — no se pisa el motivo original.');
            $this->line('  motivo: ' . ($d['motivo'] ?? '?'));
            $this->line('  quién:  ' . ($d['quien'] ?? '?'));
            $this->line('  cuándo: ' . ($d['cuando'] ?? '?'));

            return self::SUCCESS;
        }

        FrenoCircuito::poner($motivo, $quien);

        $this->info('FRENO PUESTO. El centinela existe → el circuito está detenido.');
        $this->line('  archivo: ' . FrenoCircuito::ruta());
        $this->line('  motivo:  ' . $motivo);
        $this->line('  quién:   ' . $quien);
        $this->newLine();
        $this->line('Las vueltas en curso lo ven en su SIGUIENTE iteración del pool y sueltan el slot.');

        return self::SUCCESS;
    }
}
