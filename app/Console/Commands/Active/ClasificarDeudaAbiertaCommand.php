<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\ClasificadorDeuda;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Item #571 (fase 3), solo lectura: separa las proformas abiertas en deuda real y deuda
 * fantasma. Por defecto revisa los clientes con dos o más proformas del mismo vencimiento y
 * alguna abierta (el rastro del error del cobro en caja); con --todos, a todo cliente con una
 * proforma abierta.
 *
 * La clasificación vive en ClasificadorDeuda (la comparten las correcciones automáticas).
 *
 * Deja un CSV con nombres (no se versiona) y un lote para facturacion:reacomodar-pagos-lote con
 * las correcciones que se pueden derivar solas, sin aprobar: cada fila se marca a mano.
 */
class ClasificarDeudaAbiertaCommand extends Command
{
    protected $signature = 'facturacion:clasificar-deuda
        {--todos : todos los clientes con una proforma abierta, no solo los de vencimiento repetido}
        {--cliente=* : agrega un cliente por id; se puede repetir}
        {--hoy= : fecha de referencia AAAA-MM-DD (por defecto, hoy)}';

    protected $description = 'Clasifica las proformas abiertas en deuda real o fantasma y arma el lote de correcciones (item #571)';

    public function handle(ClasificadorDeuda $clasificador): int
    {
        $hoy = $this->option('hoy') ? Carbon::parse($this->option('hoy'))->startOfDay() : Carbon::today();
        $clientes = $clasificador->poblacion((bool) $this->option('todos'), (array) $this->option('cliente'));
        if ($clientes->isEmpty()) {
            $this->info('No hay clientes que revisar.');
            return self::SUCCESS;
        }

        $filas = [];
        $lote = [];
        foreach ($clientes as $id) {
            $r = $clasificador->clasificar((int) $id, $hoy);
            $filas[] = $r;
            if ($r['propuesta']) {
                $p = $r['propuesta'];
                $lote[] = [$r['cliente'], $p['desde'], $p['pagos_desde'], implode(' ', $p['anular']), $p['motivo'], ''];
            }
        }

        $this->table(['Categoría', 'Clientes'], collect($filas)->countBy('categoria')->sortDesc()->map(fn ($n, $c) => [$c, $n])->values()->all());
        foreach ($filas as $r) {
            if ($r['categoria'] !== ClasificadorDeuda::DEBE && $r['categoria'] !== ClasificadorDeuda::AL_CORRIENTE) {
                $this->line("{$r['cliente']} [{$r['categoria']}] {$r['detalle']}");
            }
        }

        $base = 'reportes/facturacion/clasificacion-deuda-' . now()->format('Ymd-His');
        Storage::disk('local')->put($base . '.csv', $this->csv(
            ['cliente', 'nombre', 'estado', 'fecha_corte', 'saldo_estado_cuenta', 'categoria', 'detalle'],
            array_map(fn ($r) => [$r['cliente'], $r['nombre'], $r['estado'], $r['fecha_corte'], $r['saldo'], $r['categoria'], $r['detalle']], $filas)
        ));
        Storage::disk('local')->put($base . '-lote.csv', $this->csv(
            ['cliente', 'desde', 'pagos_desde', 'anular', 'motivo', 'aprobado'],
            $lote
        ));

        $this->info("Clasificación: storage/app/{$base}.csv (lleva nombres: no se sube al repo).");
        $this->info("Lote propuesto (" . count($lote) . " filas, ninguna aprobada): storage/app/{$base}-lote.csv");
        $this->line('Revísalo, pon «si» en la columna aprobado de cada fila revisada y córrelo con facturacion:reacomodar-pagos-lote.');

        return self::SUCCESS;
    }

    private function csv(array $encabezado, array $filas): string
    {
        $h = fopen('php://temp', 'r+');
        fputcsv($h, $encabezado);
        foreach ($filas as $fila) {
            fputcsv($h, $fila);
        }
        rewind($h);

        return stream_get_contents($h);
    }
}
