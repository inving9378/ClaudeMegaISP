<?php

namespace App\Console\Commands\Active;

use App\Models\ClientHistorialCambio;
use App\Services\Clientes\HistorialCliente;
use App\Services\Finance\Billing\FotoReglas;
use App\Services\Finance\Billing\ReglasFacturacion;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * FR-08 (#542, decisión de Irving 2026-10-17): pasa a Cancelado (baja definitiva) a los Inactivos
 * antiguos — más de 12 meses inactivos o sin adeudo. Por defecto SOLO LISTA (dry-run). Aplica solo
 * con --aplicar y --confirmar=CANCELAR. Los que no tienen fecha de inactivación conocida no entran
 * salvo --incluir-sin-fecha. Cada cambio va por el modelo: queda en el historial (origen sistema) y
 * genera el estado de cuenta final de cada cliente (sin enviarlo). Deja un CSV con lo aplicado.
 *
 *   php artisan facturacion:inactivos-a-cancelado                       (lista, no cambia nada)
 *   php artisan facturacion:inactivos-a-cancelado --aplicar --confirmar=CANCELAR [--limite=500]
 */
class InactivosACanceladoCommand extends Command
{
    public const CONFIRMACION = 'CANCELAR';

    protected $signature = 'facturacion:inactivos-a-cancelado
                            {--aplicar : Hace el cambio (sin esto solo lista)}
                            {--confirmar= : Debe ser CANCELAR para aplicar}
                            {--incluir-sin-fecha : Incluye a los inactivos con adeudo sin fecha de inactivación conocida}
                            {--limite=0 : Máximo de clientes a cambiar en esta corrida (0 = todos)}
                            {--hoy= : Fecha de referencia AAAA-MM-DD}';

    protected $description = 'Lista (o, con confirmación, pasa a Cancelado) a los inactivos antiguos';

    public function handle(): int
    {
        $hoy = $this->option('hoy') ? Carbon::parse($this->option('hoy'))->startOfDay() : Carbon::today();
        $aplicar = (bool) $this->option('aplicar');
        if ($aplicar && $this->option('confirmar') !== self::CONFIRMACION) {
            $this->error('Para aplicar escribe --confirmar=' . self::CONFIRMACION . '. No se cambió nada.');

            return self::FAILURE;
        }

        $motivos = ['sin_adeudo' => 'sin adeudo', 'mas_de_12_meses' => 'más de 12 meses inactivo', 'sin_fecha' => 'sin fecha de inactivación'];
        $candidatos = [];
        $cuenta = array_fill_keys(array_keys($motivos), 0);
        foreach (FotoReglas::clientes($hoy) as $f) {
            $m = FotoReglas::motivoInactivoACancelado($f, $hoy);
            if (! $m) {
                continue;
            }
            $cuenta[$m]++;
            if ($m === 'sin_fecha' && ! $this->option('incluir-sin-fecha')) {
                continue;
            }
            $candidatos[] = $f + ['motivo' => $m];
        }

        $this->table(['Motivo', 'Inactivos', 'Entran'], array_map(fn ($k) => [$motivos[$k], $cuenta[$k],
            $k === 'sin_fecha' && ! $this->option('incluir-sin-fecha') ? 'no (usa --incluir-sin-fecha)' : 'sí'], array_keys($motivos)));
        $this->line('Entran en total: ' . count($candidatos) . '.');

        if (! $aplicar) {
            $this->line('Solo lista (dry-run): no se cambió nada. Para aplicar: --aplicar --confirmar=' . self::CONFIRMACION . '.');

            return self::SUCCESS;
        }

        $limite = max(0, (int) $this->option('limite'));
        $lote = $limite ? array_slice($candidatos, 0, $limite) : $candidatos;
        $csv = "cliente,motivo,adeudo,inactivo_desde,resultado\n";
        $hechos = 0;
        $barra = $this->output->createProgressBar(count($lote));
        foreach ($lote as $f) {
            $resultado = 'sin cambio';
            try {
                // Revalida justo antes de cambiar: solo si sigue Inactivo.
                $info = \App\Models\ClientMainInformation::where('client_id', $f['client_id'])->first();
                if ($info && $info->estado === ReglasFacturacion::INACTIVO) {
                    HistorialCliente::conOrigen(ClientHistorialCambio::ORIGEN_SISTEMA,
                        fn () => $info->update(['estado' => ReglasFacturacion::CANCELADO]),
                        'Inactivo antiguo pasado a Cancelado (' . $motivos[$f['motivo']] . ')');
                    $resultado = 'cancelado';
                    $hechos++;
                }
            } catch (\Throwable $e) {
                $resultado = 'error: ' . str_replace(["\n", ','], ' ', mb_substr($e->getMessage(), 0, 150));
            }
            $csv .= implode(',', [$f['client_id'], $f['motivo'], $f['adeudo'],
                $f['inactivo_desde'] ? $f['inactivo_desde']->toDateString() : '', $resultado]) . "\n";
            $barra->advance();
        }
        $barra->finish();
        $this->line('');
        $ruta = 'simulaciones/inactivos-a-cancelado-' . now()->format('Ymd-His') . '.csv';
        Storage::disk('local')->put($ruta, $csv);
        $this->info("Pasaron a Cancelado: {$hechos}. Detalle: storage/app/{$ruta}");

        return self::SUCCESS;
    }
}
