<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\ReacomodoPagos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Item #1089 (decisión de Irving en #573), SOLO LECTURA: calcula cuánta deuda sin pagar se quitaría
 * a los clientes hoy bloqueados por los meses completos sin servicio. No escribe nada.
 *
 * Reglas: toda la historia de cargos; el mes en que se bloqueó SÍ se cobra (el cargo prepago
 * anterior a la suspensión es ese mes); lo ya cobrado se queda (solo se quita deuda NO pagada).
 *
 * Método: la deuda viva es el saldo negativo de `balances`; se asigna a los cargos de servicio más
 * nuevos (los que quedan sin pagar al repartir los abonos del más viejo al más nuevo). De esa deuda
 * se quitan los cargos con fecha igual o posterior a clients.fecha_suspension. Solo hay fecha de la suspensión
 * vigente: los bloqueos anteriores ya reactivados no se pueden reconstruir y no entran.
 */
class SimularAjusteMesesBloqueadosCommand extends Command
{
    protected $signature = 'facturacion:simular-meses-bloqueados {--cliente=* : limita a estos clientes}';

    protected $description = 'Simula (solo lectura) quitar la deuda sin pagar de meses bloqueados (item #1089)';

    public function handle(): int
    {
        $clientes = DB::table('clients as c')
            ->join('client_main_information as m', 'm.client_id', '=', 'c.id')
            ->where('m.estado', 'Bloqueado')->whereNull('c.deleted_at')
            ->when($this->option('cliente'), fn ($q, $ids) => $q->whereIn('c.id', array_map('intval', $ids)))
            ->orderBy('c.id')->get(['c.id', 'c.fecha_suspension']);

        $filas = [];
        $sinFecha = 0;
        $debeTotal = $quitaTotal = $meses = 0;

        foreach ($clientes as $cl) {
            if (! $cl->fecha_suspension) {
                $sinFecha++;
                continue;
            }
            // Deuda viva = lo que el estado de cuenta (tabla de saldos) dice que debe; se asigna a los
            // cargos de servicio más nuevos, que son los que quedan sin pagar al repartir del más viejo.
            $saldo = (float) DB::table('balances')->where('balanceable_id', $cl->id)
                ->whereIn('balanceable_type', ReacomodoPagos::TIPOS_CLIENTE)->value('amount');
            $resto = $saldo < 0 ? -$saldo : 0.0;
            $cargos = DB::table('transactions')->where('client_id', $cl->id)->whereNull('deleted_at')
                ->where('type', 'debit')->where('category', 'Servicio')->orderByDesc('date')->orderByDesc('id')->get(['date', 'debit']);

            $deuda = $resto;
            $quita = $n = 0;
            foreach ($cargos as $c) {
                if ($resto <= 0) {
                    break;
                }
                $sinPagar = round(min($resto, (float) $c->debit), 2);
                $resto -= $sinPagar;
                if ($sinPagar > 0 && $c->date >= $cl->fecha_suspension) {
                    $quita += $sinPagar;
                    $n++;
                }
            }
            if ($deuda <= 0) {
                continue;
            }
            $filas[] = [$cl->id, substr($cl->fecha_suspension, 0, 10), round($deuda, 2), round($quita, 2), round($deuda - $quita, 2), $n];
            $debeTotal += $deuda;
            $quitaTotal += $quita;
            $meses += $n;
        }

        $afectados = count(array_filter($filas, fn ($f) => $f[3] > 0));
        $this->table(['Concepto', 'Valor'], [
            ['Bloqueados revisados', $clientes->count()],
            ['Sin fecha de suspensión (no evaluables)', $sinFecha],
            ['Bloqueados con deuda sin pagar', count($filas)],
            ['Deuda sin pagar actual', number_format($debeTotal, 2)],
            ['Deuda que se quitaría', number_format($quitaTotal, 2)],
            ['Deuda que quedaría', number_format($debeTotal - $quitaTotal, 2)],
            ['Meses (cargos) a quitar', $meses],
            ['Clientes afectados', $afectados],
        ]);

        $ruta = 'reportes/facturacion/simulacion-meses-bloqueados-' . now()->format('Ymd-His') . '.csv';
        $h = fopen('php://temp', 'r+');
        fputcsv($h, ['cliente', 'fecha_suspension', 'deuda_actual', 'deuda_a_quitar', 'deuda_resultante', 'meses_a_quitar']);
        foreach ($filas as $f) {
            fputcsv($h, $f);
        }
        rewind($h);
        Storage::disk('local')->put($ruta, stream_get_contents($h));
        fclose($h);
        $this->info("Reporte por cliente: storage/app/{$ruta} (solo ids, sin nombres). No se escribió nada en la base.");

        return self::SUCCESS;
    }
}
