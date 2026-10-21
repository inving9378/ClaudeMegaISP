<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Item #571 (fase 3): corre facturacion:reacomodar-pagos para cada fila de un lote (el CSV que
 * deja facturacion:clasificar-deuda: cliente, desde, pagos_desde, anular, motivo, aprobado).
 *
 * Sin --aplicar, todas las filas van en simulación: es la revisión. Con --aplicar solo se
 * escriben las filas con «si» en aprobado; las demás se saltan. Cada fila escrita deja su propio
 * respaldo y su SQL para deshacer (los del comando por cliente).
 */
class ReacomodarPagosLoteCommand extends Command
{
    protected $signature = 'facturacion:reacomodar-pagos-lote
        {archivo : CSV del lote (ruta dentro de storage/app o absoluta)}
        {--aplicar : escribe las filas aprobadas; sin esto, todas en simulación}';

    protected $description = 'Corre el reacomodo de pagos para un lote de clientes revisado (item #571)';

    private const COLUMNAS = ['cliente', 'desde', 'pagos_desde', 'anular', 'motivo', 'aprobado'];

    public function handle(): int
    {
        $ruta = $this->argument('archivo');
        $ruta = is_file($ruta) ? $ruta : Storage::disk('local')->path($ruta);
        if (! is_file($ruta)) {
            $this->error("No encuentro el lote {$this->argument('archivo')}.");
            return self::FAILURE;
        }

        $h = fopen($ruta, 'r');
        $encabezado = array_map('trim', fgetcsv($h) ?: []);
        if ($encabezado !== self::COLUMNAS) {
            $this->error('El lote debe tener las columnas: ' . implode(',', self::COLUMNAS) . '.');
            return self::FAILURE;
        }
        $filas = [];
        while (($valores = fgetcsv($h)) !== false) {
            if (count($valores) === count(self::COLUMNAS)) {
                $filas[] = array_combine(self::COLUMNAS, array_map('trim', $valores));
            }
        }
        fclose($h);

        $aplicar = (bool) $this->option('aplicar');
        $resumen = [];
        foreach ($filas as $fila) {
            $aprobada = mb_strtolower($fila['aprobado']) === 'si' || mb_strtolower($fila['aprobado']) === 'sí';
            if ($aplicar && ! $aprobada) {
                $resumen[] = [$fila['cliente'], 'saltada (sin aprobar)'];
                continue;
            }

            $this->line('');
            $this->line(str_repeat('─', 30) . " cliente {$fila['cliente']}");
            $parametros = [
                'cliente' => $fila['cliente'], '--desde' => $fila['desde'], '--pagos-desde' => $fila['pagos_desde'],
                '--anular' => array_values(array_filter(preg_split('/[\s|;]+/', $fila['anular']))),
                '--motivo' => $fila['motivo'],
            ];
            if (! $aplicar) {
                $parametros['--dry-run'] = true;
            }
            $codigo = $this->call('facturacion:reacomodar-pagos', $parametros);
            $resumen[] = [$fila['cliente'], $codigo === self::SUCCESS ? ($aplicar ? 'aplicada' : 'simulada') : 'DETENIDA (ver arriba)'];
        }

        $this->line('');
        $this->table(['Cliente', $aplicar ? 'Resultado' : 'Simulación'], $resumen);
        if (! $aplicar) {
            $this->warn('Nada se escribió. Marca «si» en aprobado y vuelve a correr con --aplicar.');
        }

        return collect($resumen)->contains(fn ($r) => str_starts_with($r[1], 'DETENIDA')) ? self::FAILURE : self::SUCCESS;
    }
}
