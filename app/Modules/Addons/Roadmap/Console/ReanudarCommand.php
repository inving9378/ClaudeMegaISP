<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Support\FrenoCircuito;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SOLTAR EL FRENO (#170) — y NO es simétrico con ponerlo.
 *
 * Poner el freno funciona siempre, incluso con la base caída: es la dirección segura. Soltarlo
 * EXIGE que el sistema esté sano, porque soltar el freno es autorizar a seis terminales con
 * permiso de escritura sobre el repo a volver a trabajar.
 *
 * Dos condiciones, y las dos explican por qué se niegan:
 *  · La base responde.
 *  · La tabla `migrations` existe. Es el canario del esquema: si falta, la base que contesta NO es
 *    la base del sistema (apuntamos a otra, o está a medio restaurar), y arrancar seis terminales
 *    contra ella es peor que dejarlas paradas.
 *
 * Lo que este comando NO hace: tocar `settings.circuito_pausado`. Si Irving frenó desde la Torre,
 * se suelta desde la Torre — el candado #342 dice que el kill switch de la UI sólo lo mueve un
 * humano autenticado con permiso, y la consola no tiene sesión. Este comando sólo retira el
 * centinela; si además hay pausa en base, lo dice y sale.
 */
class ReanudarCommand extends Command
{
    protected $signature = 'circuito:reanudar {--forzar : Retira el centinela aunque los chequeos de salud fallen}';

    protected $description = 'Suelta el freno de mano del circuito retirando el centinela (#170).';

    public function handle(): int
    {
        if (! FrenoCircuito::activo()) {
            $this->info('El freno no estaba puesto (no hay centinela). Nada que soltar.');
            $this->avisarPausaEnBase();

            return self::SUCCESS;
        }

        $d = FrenoCircuito::detalle();
        $this->line('Freno vigente:');
        $this->line('  motivo: ' . ($d['motivo'] ?? '?'));
        $this->line('  quién:  ' . ($d['quien'] ?? '?'));
        $this->line('  cuándo: ' . ($d['cuando'] ?? '?'));
        $this->newLine();

        $fallo = $this->chequearSalud();
        if ($fallo !== null && ! $this->option('forzar')) {
            $this->error('ME NIEGO A SOLTAR EL FRENO: ' . $fallo);
            $this->newLine();
            $this->line('Soltar el freno pone a trabajar a seis terminales con permiso de escritura');
            $this->line('sobre el repositorio. Con el sistema en este estado, pararlas es lo correcto.');
            $this->line('Arregla lo de arriba y vuelve a intentar, o usa --forzar si sabes lo que haces.');

            return self::FAILURE;
        }

        if ($fallo !== null) {
            $this->warn('--forzar: se suelta el freno PESE A: ' . $fallo);
        }

        FrenoCircuito::quitar();
        $this->info('FRENO SOLTADO. El centinela ya no existe.');
        $this->avisarPausaEnBase();

        return self::SUCCESS;
    }

    /** null = sano. Un string = el motivo por el que no se suelta, en palabras. */
    private function chequearSalud(): ?string
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            return 'la base de datos no responde (' . mb_strimwidth($e->getMessage(), 0, 120, '…') . ').';
        }

        try {
            if (! Schema::hasTable('migrations')) {
                return 'la base responde pero NO tiene la tabla `migrations`. Eso no es la base del '
                     . 'sistema: o apunta a otro esquema, o está a medio restaurar.';
            }
        } catch (\Throwable $e) {
            return 'no se pudo comprobar el esquema (' . mb_strimwidth($e->getMessage(), 0, 120, '…') . ').';
        }

        return null;
    }

    /**
     * El centinela y la fila de `settings` son DOS frenos, y `isPaused()` es un OR. Retirar uno
     * con el otro puesto deja el circuito parado y a quien corrió el comando creyendo que lo soltó.
     */
    private function avisarPausaEnBase(): void
    {
        try {
            if ((string) DB::table('settings')->where('key', 'circuito_pausado')->value('value') === '1') {
                $this->newLine();
                $this->warn('OJO: el circuito SIGUE PAUSADO por el freno de la Torre (settings.circuito_pausado=1).');
                $this->line('Ése no se suelta desde consola por diseño (#342): sólo un humano autenticado');
                $this->line('con permiso `circuito.pause` puede moverlo, desde la Torre.');
            }
        } catch (\Throwable) {
            // Si la base no contesta no hay nada que avisar: el propio chequeo de salud ya lo dijo.
        }
    }
}
