<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\FotoReglas;
use App\Services\Finance\Billing\ReglasFacturacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FR-08 (#542): diagnóstico de SOLO LECTURA de los datos que usan las reglas de facturación y de
 * contrato: clientes sin plazo (cuentan como 18 meses), sin fecha de activación válida (el contrato
 * empieza en la fecha de alta), prepago personalizado con corte en días 29-31 (se recorre, #544),
 * inactivos sin fecha de inactivación y cancelados sin estado de cuenta final.
 *
 *   php artisan facturacion:diagnostico-datos [--ids]   (--ids lista hasta 50 números por grupo)
 */
class DiagnosticoDatosFacturacionCommand extends Command
{
    protected $signature = 'facturacion:diagnostico-datos {--ids : Muestra hasta 50 números de cliente por grupo}';

    protected $description = 'Diagnóstico (solo lectura) de los datos de plazo, activación, corte e inactivación';

    public function handle(): int
    {
        $foto = FotoReglas::clientes(Carbon::today());
        $grupos = [
            'Sin plazo de contrato (cuentan como 18 meses)' => [],
            'Fecha de activación «now»' => [],
            'Fecha de activación vacía' => [],
            'Fecha de activación en otro formato' => [],
            'Prepago personalizado con corte en día 29-31' => [],
            'Inactivos con adeudo sin fecha de inactivación' => [],
        ];
        foreach ($foto as $f) {
            if ($f['sin_plazo']) {
                $grupos['Sin plazo de contrato (cuentan como 18 meses)'][] = $f['client_id'];
            }
            if (! $f['activacion_valida']) {
                $a = trim($f['activacion']);
                $k = $a === '' ? 'Fecha de activación vacía' : (strtolower($a) === 'now' ? 'Fecha de activación «now»' : 'Fecha de activación en otro formato');
                $grupos[$k][] = $f['client_id'];
            }
            if ($f['tipo'] === 3 && preg_match('/^\d{4}-\d{2}-(29|30|31)/', $f['fecha_corte'])) {
                $grupos['Prepago personalizado con corte en día 29-31'][] = $f['client_id'];
            }
            if ($f['estado'] === ReglasFacturacion::INACTIVO && $f['adeudo'] > 0 && ! $f['inactivo_desde']) {
                $grupos['Inactivos con adeudo sin fecha de inactivación'][] = $f['client_id'];
            }
        }

        $cancelados = DB::table('client_main_information as m')->join('clients as c', 'c.id', '=', 'm.client_id')
            ->whereNull('c.deleted_at')->where('m.estado', ReglasFacturacion::CANCELADO)
            ->whereNotExists(fn ($q) => $q->from('client_estados_cuenta as e')->whereColumn('e.client_id', 'm.client_id')->where('e.tipo', 'final'))
            ->pluck('m.client_id')->all();
        $grupos['Cancelados sin estado de cuenta final (cancelados antes de esta versión)'] = $cancelados;

        $this->table(['Grupo', 'Clientes'] + ($this->option('ids') ? [2 => 'Números (hasta 50)'] : []),
            array_map(fn ($k) => array_merge([$k, count($grupos[$k])],
                $this->option('ids') ? [implode(', ', array_slice($grupos[$k], 0, 50))] : []), array_keys($grupos)));
        $this->line('Solo lectura: no se cambió nada. Total de clientes revisados: ' . count($foto) . '.');

        return self::SUCCESS;
    }
}
