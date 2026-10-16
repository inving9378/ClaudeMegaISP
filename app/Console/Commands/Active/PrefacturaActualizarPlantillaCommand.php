<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\PrefacturaPlantillaUpdater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Aplica a mano el mismo ajuste de la plantilla «Prefactura.» que hace la migración
 * 2026_10_16_170000 (item #480), para cuando esa migración hizo no-op porque el HTML en BD no
 * era el esperado. Busca por nombre, nunca por id.
 *
 *   --dry-run  muestra la situación y el diff; no escribe.
 *   --force    aplica aunque el HTML no sea el original esperado (solo si están TODAS las anclas).
 *
 * Antes de escribir respalda el HTML previo en storage/app/prefactura/.
 */
class PrefacturaActualizarPlantillaCommand extends Command
{
    protected $signature = 'prefactura:actualizar-plantilla
        {--dry-run : Muestra el diff sin escribir}
        {--force : Aplica aunque el HTML no sea el original esperado}';

    protected $description = 'Item #480 — ajusta la plantilla «Prefactura.» para el PrefacturaDataProvider (logo, IVA en pesos, comentarios).';

    public function handle(PrefacturaPlantillaUpdater $updater): int
    {
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $previa = $updater->aplicar($force, true);
        $this->line('Plantilla: «' . PrefacturaPlantillaUpdater::NOMBRE . '»' . ($previa['id'] ? " (id {$previa['id']} en esta BD)" : ''));
        $this->line('Situación: ' . $previa['situacion']);

        foreach ($previa['aplicados'] as $cambio) {
            $this->line("  ✓ {$cambio}");
        }
        foreach ($previa['faltan'] as $falta) {
            $this->warn("  ✗ {$falta}");
        }

        if ($previa['despues'] !== null) {
            $diff = PrefacturaPlantillaUpdater::diff($previa['antes'], $previa['despues']);
            if ($diff) {
                $this->newLine();
                foreach ($diff as $linea) {
                    match ($linea[0]) {
                        '-'     => $this->line("<fg=red>{$linea}</>"),
                        '+'     => $this->line("<fg=green>{$linea}</>"),
                        default => $this->line("<fg=cyan>{$linea}</>"),
                    };
                }
                $this->newLine();
            }
        }

        $aplicable = $previa['despues'] !== null && ! $previa['faltan']
            && ($previa['situacion'] === PrefacturaPlantillaUpdater::SITUACION_ORIGINAL || $force);

        if ($dryRun || ! $aplicable) {
            $this->info($previa['motivo']);

            return $aplicable || $previa['situacion'] === PrefacturaPlantillaUpdater::SITUACION_APLICADA
                ? self::SUCCESS
                : self::FAILURE;
        }

        $respaldo = sprintf('prefactura/plantilla-%d-%s.html', $previa['id'], now()->format('Ymd-His'));
        Storage::disk('local')->put($respaldo, (string) $previa['antes']);
        $this->line('Respaldo del HTML previo: ' . Storage::disk('local')->path($respaldo));

        $r = $updater->aplicar($force, false);
        if (! $r['escrito']) {
            $this->error('No se escribió: ' . $r['motivo']);

            return self::FAILURE;
        }

        $this->info($r['motivo']);

        return self::SUCCESS;
    }
}
