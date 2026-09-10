<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Services\ProvisionadorAsterisk;
use Illuminate\Console\Command;

/**
 * Punto de entrada de la provisión. Lo invoca el proceso de actualización de
 * MegaISP, que ya corre con privilegios en el servidor.
 */
class ProvisionarAsteriskCommand extends Command
{
    protected $signature = 'voip:provisionar
                            {--descubrimiento : Primera vez: no exige esquema_realtime, lo reporta}
                            {--estado= : Solo muestra el estado de una ejecución por UUID}';

    protected $description = 'Instala y configura Asterisk según config/requisitos-voip.php.';

    public function handle(): int
    {
        if ($uuid = $this->option('estado')) {
            return $this->mostrarEstado($uuid);
        }

        $descubrimiento = (bool) $this->option('descubrimiento');

        if ($descubrimiento) {
            $this->warn('MODO DESCUBRIMIENTO: se aceptará la revisión de Alembic que resulte y se reportará.');
            $this->warn('No usar en la instalación de un cliente: ahí el manifiesto viene completo.');
        }

        $p = new ProvisionadorAsterisk($descubrimiento, fn (string $m) => $this->line($m));
        $r = $p->ejecutar();

        $this->newLine();
        $this->table(['paso', 'estado', 'versión', 'error'],
            collect($r['pasos'])->map(fn ($d, $paso) => [
                $paso, $d['estado'], $d['version'], \Illuminate\Support\Str::limit($d['error'] ?? '', 46),
            ])->values()->all());

        $this->newLine();

        if (! $r['ok']) {
            $this->error($r['mensaje']);
            $this->line("Reintentar: php artisan voip:provisionar   (retoma desde donde se quedó)");
            $this->line("Ver estado: php artisan voip:provisionar --estado={$r['uuid']}");

            return self::FAILURE;
        }

        $this->info($r['mensaje']);

        if ($descubrimiento && $r['revision']) {
            $this->newLine();
            $this->line('  ┌─────────────────────────────────────────────────────────────┐');
            $this->line('  │ FIJA ESTO EN config/requisitos-voip.php                     │');
            $this->line("  │   'esquema_realtime' => '{$r['revision']}',");
            $this->line('  │ A partir de ahí se valida contra él y una revisión distinta │');
            $this->line('  │ aborta en vez de aplicarse en silencio.                     │');
            $this->line('  └─────────────────────────────────────────────────────────────┘');
        }

        return self::SUCCESS;
    }

    private function mostrarEstado(string $uuid): int
    {
        $filas = \App\Modules\Addons\VoIP\Models\ProvisionEstado::deEjecucion($uuid)->orderBy('id')->get();

        if ($filas->isEmpty()) {
            $this->error("No hay registros para la ejecución {$uuid}.");

            return self::FAILURE;
        }

        $this->table(['paso', 'estado', 'versión', 'intentos', 'terminado'],
            $filas->map(fn ($f) => [$f->paso, $f->estado, $f->version_provisionador, $f->intentos, $f->terminado_at])->all());

        return self::SUCCESS;
    }
}
