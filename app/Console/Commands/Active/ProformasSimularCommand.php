<?php

namespace App\Console\Commands\Active;

use App\Models\Invoice;
use App\Modules\Core\Clientes\Models\ClientBillingPause;
use App\Modules\Core\Configuracion\Repositories\ConfigFinanceNotificationRepository;
use App\Services\Finance\Invoice\EnvioPrefactura;
use App\Services\Finance\Invoice\ProformaAnticipada as P;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAC-06 (item #512): simulación de SOLO LECTURA de «proforma N días antes del corte» contra la
 * regla actual (todas el día 25 del mes anterior). No crea facturas ni cambia nada.
 *
 * Para cada cliente con corte en los próximos --horizonte días: igual / adelantada / atrasada
 * (cuántos días) o excluida (fecha de corte inválida, corte ya pasado, factura del periodo ya
 * creada, pausa de facturación en curso). Además propone N a partir de la anticipación que hoy
 * recibe cada cliente (mediana y cuartiles).
 */
class ProformasSimularCommand extends Command
{
    protected $signature = 'invoice:proformas-simular
                            {--dias= : N a simular para todos (por defecto, el «Envío prefactura» de cada cliente o el general)}
                            {--horizonte=45 : Días hacia adelante que se revisan}
                            {--hoy= : Fecha de referencia AAAA-MM-DD (por defecto hoy)}';

    protected $description = 'Simula (solo lectura) la proforma con «Envío prefactura» (días antes del día de facturación) contra la regla actual del día 25';

    public function handle(ConfigFinanceNotificationRepository $configs): int
    {
        $hoy = $this->option('hoy') ? Carbon::parse($this->option('hoy'))->startOfDay() : Carbon::today();
        $horizonte = max(1, (int) $this->option('horizonte'));
        // FR-05 (#539): N = «Envío prefactura» de cada cliente (o el general), contado desde su día de
        // facturación; --dias lo fija igual para todos.
        $general = EnvioPrefactura::diasPorDefecto();
        $n = null;
        if ($this->option('dias') !== null) {
            $n = P::dias($this->option('dias'));
            if ($n === null) {
                $this->error('--dias no es válido; usa --dias=0..' . P::MAX_DIAS . '.');

                return self::FAILURE;
            }
        }
        $origenN = $n !== null ? "N={$n} (--dias)" : "N = Envío prefactura de cada cliente (general: {$general})";

        $this->info("Simulación (solo lectura): hoy={$hoy->toDateString()}, {$origenN}, horizonte={$horizonte} días, interruptor="
            . (config('billing.proforma_anticipada') ? 'ENCENDIDO' : 'apagado') . '.');

        $limite = $hoy->copy()->addDays($horizonte);
        $propios = Schema::hasColumn('billing_configurations', 'envio_prefactura_dias') ? 'bc.envio_prefactura_dias' : DB::raw('NULL as envio_prefactura_dias');
        $clientes = DB::table('clients as c')
            ->leftJoin('billing_configurations as bc', 'bc.client_id', '=', 'c.id')
            ->whereExists(fn ($q) => $q->from('client_main_information as m')->whereColumn('m.client_id', 'c.id')
                ->where('m.estado', '!=', 'Cancelado')) // FR-02 (#536): Cancelado no recibe prefactura
            ->whereNotNull('c.fecha_corte')->where('c.fecha_corte', '!=', '')
            ->get(['c.id', 'c.fecha_corte', 'c.fecha_pago', 'bc.type_billing_id', $propios]);

        $pausados = array_flip(ClientBillingPause::where('estado', ClientBillingPause::ESTADO_EN_CURSO)->pluck('client_id')->all());
        $conFactura = [];
        Invoice::query()->where('period', '>=', $hoy->format('Y-m'))->where('period', '<=', $limite->format('Y-m'))
            ->select(['client_id', 'period'])->orderBy('id')
            ->chunk(5000, function ($filas) use (&$conFactura) {
                foreach ($filas as $f) {
                    $conFactura[$f->client_id . '|' . $f->period] = true;
                }
            });

        $cuenta = [P::IGUAL => 0, P::ADELANTADA => 0, P::ATRASADA => 0];
        $excluidas = ['fecha de corte inválida' => 0, 'fecha de facturación inválida' => 0, 'corte ya pasado (sin retroactivo)' => 0,
            'ya tiene factura del periodo' => 0, 'pausa de facturación en curso' => 0];
        $fueraDeHorizonte = 0;
        $difs = [];
        $anticipaciones = [];

        foreach ($clientes as $c) {
            $corte = P::corte($c->fecha_corte);
            if (! $corte) {
                $excluidas['fecha de corte inválida']++;
                continue;
            }
            if ($corte->lt($hoy)) {
                $excluidas['corte ya pasado (sin retroactivo)']++;
                continue;
            }
            if ($corte->gt($limite)) {
                $fueraDeHorizonte++;
                continue;
            }
            $anticipaciones[] = P::anticipacionActual($corte);
            if (isset($pausados[$c->id])) {
                $excluidas['pausa de facturación en curso']++;
                continue;
            }
            if (isset($conFactura[$c->id . '|' . P::periodo($corte)])) {
                $excluidas['ya tiene factura del periodo']++;
                continue;
            }
            $facturacion = EnvioPrefactura::fechaDeFacturacion($c, $c);
            if (! $facturacion) {
                $excluidas['fecha de facturación inválida']++;
                continue;
            }
            [$clase, $dif] = P::comparar($corte, $n ?? EnvioPrefactura::diasDe($c, $general), $hoy, $facturacion);
            $cuenta[$clase]++;
            $difs[] = $dif;
        }

        $this->table(['Resultado', 'Clientes'], array_merge(
            [['iguales', $cuenta[P::IGUAL]], ['adelantadas', $cuenta[P::ADELANTADA]], ['atrasadas', $cuenta[P::ATRASADA]]],
            array_map(fn ($k, $v) => ["excluida · {$k}", $v], array_keys($excluidas), $excluidas),
            [['(corte después del horizonte, no evaluado)', $fueraDeHorizonte]]
        ));

        if ($difs) {
            sort($difs);
            $this->line('Diferencia nueva − actual (días; negativo = antes): mín ' . $difs[0] . ', mediana ' . self::percentil($difs, 50)
                . ', máx ' . end($difs) . '.');
        }
        if ($anticipaciones) {
            sort($anticipaciones);
            $p25 = self::percentil($anticipaciones, 25);
            $p50 = self::percentil($anticipaciones, 50);
            $p75 = self::percentil($anticipaciones, 75);
            $this->line("Anticipación que hoy recibe cada cliente (día 25 → corte): mín {$anticipaciones[0]}, p25 {$p25}, mediana {$p50}, p75 {$p75}, máx " . end($anticipaciones) . ' días.');
            // FR-05 (#539): Irving fijó el valor general en 5 días antes del día de facturación.
            $this->line("Valor general de Envío prefactura: {$general} días (Configuración › Notificaciones de finanzas › Facturas proforma).");
        }
        $this->line('Nada se creó ni se modificó.');

        return self::SUCCESS;
    }

    private static function percentil(array $ordenado, int $p): int
    {
        $i = (int) floor(($p / 100) * (count($ordenado) - 1));

        return (int) $ordenado[$i];
    }
}
