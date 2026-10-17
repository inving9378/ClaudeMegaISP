<?php

namespace App\Console\Commands\Active;

use App\Services\Finance\Billing\CapaContrato;
use App\Services\Finance\Billing\FotoReglas;
use App\Services\Finance\Billing\ReglasFacturacion;
use App\Support\PlazoContrato;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * FR-08 (#542): simulación de SOLO LECTURA de las reglas por estado y de contrato (como si sus
 * interruptores estuvieran encendidos), con las mismas funciones que usa el cobro: quién recibe
 * prefactura, estado de cuenta o nada; contratos vigentes, renovados o cumplidos; quién llega al tope
 * de facturas sin pago; inactivos antiguos que pasarían a cancelado. No crea ni cambia nada.
 *
 *   php artisan facturacion:reglas-simular [--hoy=AAAA-MM-DD] [--detalle]
 *   --detalle guarda un CSV por cliente (solo número de cliente y resultados) en storage/app/simulaciones/
 */
class ReglasFacturacionSimularCommand extends Command
{
    protected $signature = 'facturacion:reglas-simular
                            {--hoy= : Fecha de referencia AAAA-MM-DD (por defecto hoy)}
                            {--detalle : Guarda el CSV por cliente}';

    protected $description = 'Simula (solo lectura) las reglas de facturación por estado y de contrato';

    public function handle(): int
    {
        $hoy = $this->option('hoy') ? Carbon::parse($this->option('hoy'))->startOfDay() : Carbon::today();
        $foto = FotoReglas::clientes($hoy);
        $desde = ReglasFacturacion::reglasDesde();

        $this->info("Simulación (solo lectura) al {$hoy->format('d/m/Y')}. Reglas desde: " . ($desde ? $desde->format('d/m/Y H:i') : 'sin fecha')
            . '. Interruptores: proforma anticipada ' . $this->onOff('billing.proforma_anticipada')
            . ', estado de cuenta mensual ' . $this->onOff('billing.estado_cuenta_mensual')
            . ', capa de contrato ' . $this->onOff('billing.capa_contrato') . '.');

        // 1. Documento del mes según el estado.
        $doc = ['prefactura' => 0, 'estado de cuenta · bloqueados' => 0, 'estado de cuenta · inactivos con adeudo (≤ 12 meses)' => 0,
            'nada · nuevos' => 0, 'nada · cancelados' => 0, 'nada · inactivos sin adeudo' => 0,
            'nada · inactivos con más de 12 meses' => 0, 'nada · inactivos sin fecha de inactivación' => 0, 'nada · otro estado' => 0];
        foreach ($foto as $f) {
            $doc[$this->claveDocumento($f, $hoy)]++;
        }
        $this->line('');
        $this->line('<info>1. Documento del mes según el estado</info>');
        $this->table(['Documento', 'Clientes'], $this->filas($doc));

        // 2. Contrato (pago recurrente, Activos y Bloqueados: a quienes aplica la capa de contrato).
        $con = [PlazoContrato::VIGENTE => 0, PlazoContrato::RENOVADO => 0, PlazoContrato::CUMPLIDO . ' sin autorrenovación' => 0,
            'cumplido sin autorrenovación y con adeudo' => 0, 'sin fecha de inicio' => 0];
        $tope = ['llegan al tope (pasarían a Inactivo en su siguiente ciclo)' => [], 'en camino al tope' => []];
        foreach ($foto as $f) {
            if (! $f['recurrente'] || ! in_array($f['estado'], [ReglasFacturacion::ACTIVO, ReglasFacturacion::BLOQUEADO], true)) {
                continue;
            }
            if ($f['contrato'] === PlazoContrato::CUMPLIDO) {
                $con['cumplido sin autorrenovación']++;
                if ($f['adeudo'] > 0) {
                    $con['cumplido sin autorrenovación y con adeudo']++;
                }
                if ($f['impagos'] >= $f['max_sin_pago']) {
                    $tope['llegan al tope (pasarían a Inactivo en su siguiente ciclo)'][] = $f['client_id'];
                } elseif ($f['impagos'] > 0) {
                    $tope['en camino al tope'][] = $f['client_id'];
                }
            } elseif ($f['contrato'] === 'sin_inicio') {
                $con['sin fecha de inicio']++;
            } else {
                $con[$f['contrato']]++;
            }
        }
        $this->line('');
        $this->line('<info>2. Contrato (pago recurrente, Activos y Bloqueados)</info>');
        $this->table(['Contrato', 'Clientes'], $this->filas($con));
        $this->line('');
        $this->line('<info>3. Tope de facturas sin pago (cuenta solo cargos registrados con la capa encendida)</info>');
        $this->table(['Situación', 'Clientes', 'Números de cliente (hasta 30)'], array_map(
            fn ($k) => [$k, count($tope[$k]), implode(', ', array_slice($tope[$k], 0, 30))], array_keys($tope)));

        // 4. Inactivos antiguos → cancelado (lo que haría facturacion:inactivos-a-cancelado).
        $inac = ['sin_adeudo' => 0, 'mas_de_12_meses' => 0, 'sin_fecha' => 0];
        foreach ($foto as $f) {
            $m = FotoReglas::motivoInactivoACancelado($f, $hoy);
            if ($m) {
                $inac[$m]++;
            }
        }
        $this->line('');
        $this->line('<info>4. Inactivos antiguos que pasarían a Cancelado</info>');
        $this->table(['Motivo', 'Clientes'], [
            ['sin adeudo', $inac['sin_adeudo']], ['más de 12 meses inactivo', $inac['mas_de_12_meses']],
            ['sin fecha de inactivación (no entran salvo --incluir-sin-fecha)', $inac['sin_fecha']],
        ]);

        if ($this->option('detalle')) {
            $ruta = 'simulaciones/reglas-' . now()->format('Ymd-His') . '.csv';
            $csv = "cliente,estado,tipo_facturacion,documento,contrato,fin_contrato,meses,autorrenovacion,adeudo,impagos,max_sin_pago,inactivo_a_cancelado\n";
            foreach ($foto as $f) {
                $csv .= implode(',', [$f['client_id'], $f['estado'], $f['tipo'], $f['documento'] ?? 'nada', $f['contrato'], $f['fin'] ?? '',
                    $f['meses'], $f['autorrenovacion'] ? 'si' : 'no', $f['adeudo'], $f['impagos'] ?? '', $f['max_sin_pago'] ?? '',
                    FotoReglas::motivoInactivoACancelado($f, $hoy) ?? '']) . "\n";
            }
            Storage::disk('local')->put($ruta, $csv);
            $this->line("Detalle por cliente: storage/app/{$ruta}");
        }
        $this->line('Nada se creó ni se modificó. Total de clientes: ' . count($foto) . '.');

        return self::SUCCESS;
    }

    private function claveDocumento(array $f, Carbon $hoy): string
    {
        if ($f['documento'] === ReglasFacturacion::DOC_PREFACTURA) {
            return 'prefactura';
        }
        if ($f['documento'] === ReglasFacturacion::DOC_ESTADO_CUENTA) {
            return $f['estado'] === ReglasFacturacion::BLOQUEADO ? 'estado de cuenta · bloqueados' : 'estado de cuenta · inactivos con adeudo (≤ 12 meses)';
        }
        switch ($f['estado']) {
            case ReglasFacturacion::NUEVO:
                return 'nada · nuevos';
            case ReglasFacturacion::CANCELADO:
                return 'nada · cancelados';
            case ReglasFacturacion::INACTIVO:
                if ($f['adeudo'] <= 0) {
                    return 'nada · inactivos sin adeudo';
                }

                return $f['inactivo_desde'] ? 'nada · inactivos con más de 12 meses' : 'nada · inactivos sin fecha de inactivación';
            default:
                return 'nada · otro estado';
        }
    }

    private function filas(array $cuenta): array
    {
        return array_map(fn ($k, $v) => [$k, $v], array_keys($cuenta), $cuenta);
    }

    private function onOff(string $clave): string
    {
        return config($clave) ? 'ENCENDIDO' : 'apagado';
    }
}
