<?php

namespace App\Console\Commands\Active;

use App\Models\ClientEstadoCuenta;
use App\Services\Finance\Billing\EstadoCuentaService;
use App\Services\Finance\Billing\ReglasFacturacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FR-06 (#540): estado de cuenta del mes para quien le corresponde según su estado
 * (ReglasFacturacion::documentoDelMes): Bloqueados, e Inactivos que deben algo y llevan máximo 12
 * meses inactivos. Uno por cliente y mes (clave AAAA-MM), idempotente. Genera el PDF sin valor
 * fiscal; NO lo envía. Solo desde billing_config.reglas_facturacion_desde.
 *
 *   php artisan facturacion:estados-cuenta-mensuales --simular   (solo cuenta, no genera nada)
 *   php artisan facturacion:estados-cuenta-mensuales             (requiere BILLING_ESTADO_CUENTA_MENSUAL=true)
 */
class EstadosCuentaMensualesCommand extends Command
{
    protected $signature = 'facturacion:estados-cuenta-mensuales
                            {--simular : Solo cuenta a quién le tocaría; no genera nada}
                            {--hoy= : Fecha de referencia AAAA-MM-DD (pruebas)}';

    protected $description = 'Genera el estado de cuenta del mes de bloqueados e inactivos con adeudo (no lo envía)';

    public function handle(EstadoCuentaService $servicio): int
    {
        $hoy = $this->option('hoy') ? Carbon::parse($this->option('hoy'))->startOfDay() : Carbon::today();
        $simular = (bool) $this->option('simular');

        if (! $simular && ! config('billing.estado_cuenta_mensual')) {
            $this->info('Estado de cuenta mensual apagado (BILLING_ESTADO_CUENTA_MENSUAL). No se generó nada.');

            return self::SUCCESS;
        }
        $desde = ReglasFacturacion::reglasDesde();
        if (! $desde || $hoy->lt($desde->copy()->startOfDay())) {
            $this->info('Las reglas por estado aún no aplican (reglas_facturacion_desde). No se generó nada.');

            return self::SUCCESS;
        }

        $clave = $hoy->format('Y-m');
        $cuenta = ['generados' => 0, 'ya_tenian' => 0, 'no_corresponde' => 0, 'errores' => 0];
        DB::table('client_main_information as m')
            ->join('clients as c', 'c.id', '=', 'm.client_id')
            ->whereNull('c.deleted_at')
            ->whereIn('m.estado', [ReglasFacturacion::BLOQUEADO, ReglasFacturacion::INACTIVO])
            ->orderBy('m.client_id')
            ->select('m.client_id', 'm.estado')
            ->chunk(500, function ($filas) use ($servicio, $hoy, $clave, $simular, &$cuenta) {
                foreach ($filas as $f) {
                    $id = (int) $f->client_id;
                    $doc = ReglasFacturacion::documentoDelMes($f->estado, ReglasFacturacion::adeudoDe($id),
                        $f->estado === ReglasFacturacion::INACTIVO ? ReglasFacturacion::inactivoDesde($id) : null, $hoy);
                    if ($doc !== ReglasFacturacion::DOC_ESTADO_CUENTA) {
                        $cuenta['no_corresponde']++;
                        continue;
                    }
                    $existe = ClientEstadoCuenta::where('client_id', $id)->where('tipo', ClientEstadoCuenta::TIPO_MENSUAL)
                        ->where('clave', $clave)->whereNotNull('pdf_path')->exists();
                    if ($existe) {
                        $cuenta['ya_tenian']++;
                        continue;
                    }
                    if ($simular) {
                        $cuenta['generados']++;
                        continue;
                    }
                    $e = $servicio->generar($id, ClientEstadoCuenta::TIPO_MENSUAL, $clave);
                    $e->error ? $cuenta['errores']++ : $cuenta['generados']++;
                }
            });

        $this->table(['Resultado', 'Clientes'], [
            [$simular ? 'se generarían' : 'generados', $cuenta['generados']],
            ['ya tenían el de ' . $clave, $cuenta['ya_tenian']],
            ['no les corresponde (sin adeudo, más de 12 meses o sin fecha de inactivación)', $cuenta['no_corresponde']],
            ['errores', $cuenta['errores']],
        ]);
        $this->line($simular ? 'Simulación: nada se generó.' : 'Generados, no enviados.');

        return self::SUCCESS;
    }
}
