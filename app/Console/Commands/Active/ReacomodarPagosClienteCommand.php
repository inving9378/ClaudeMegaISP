<?php

namespace App\Console\Commands\Active;

use App\Models\Balance;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\Finance\Billing\ReacomodoPagos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Item #571 (caso cliente 1286, 2026-10-21): reacomoda los pagos de UN cliente para que cada uno
 * quede en su mes. Toma sus proformas desde --desde y sus pagos desde --pagos-desde, en orden, y
 * liga el primer pago a la primera proforma, el segundo a la segunda, etc. Lo que sobra de un
 * pago queda como saldo a favor (no se reparte), y una proforma sin pago queda pendiente.
 *
 * No toca el estado de cuenta (transactions) ni el saldo, salvo el ajuste explícito de
 * --monto-pago. Antes de escribir guarda un respaldo JSON, el SQL para deshacer y el reporte en
 * storage/app/respaldos/facturacion/. Con --dry-run solo muestra el antes y el después. La lógica
 * vive en ReacomodoPagos (la comparten las correcciones automáticas).
 */
class ReacomodarPagosClienteCommand extends Command
{
    protected $signature = 'facturacion:reacomodar-pagos
        {cliente : id del cliente}
        {--desde= : primer periodo (AAAA-MM) que se reacomoda; las proformas anteriores no se tocan}
        {--pagos-desde= : fecha (AAAA-MM-DD) del primer pago que entra al reacomodo}
        {--anular=* : id de una proforma que se cancela (no se cobra); se puede repetir}
        {--monto-pago=* : PAGO=MONTO, monto real de un pago verificado con el banco; se puede repetir}
        {--motivo= : texto que queda en la nota de las facturas tocadas}
        {--dry-run : muestra el antes y el después sin escribir}';

    protected $description = 'Reacomoda los pagos de un cliente para que cada uno quede en su mes (item #571)';

    /** Líneas del reporte, también se guardan junto al respaldo. */
    private array $reporte = [];

    private ReacomodoPagos $reacomodo;

    public function handle(ReacomodoPagos $reacomodo): int
    {
        $this->reacomodo = $reacomodo;
        $cliente = Client::find((int) $this->argument('cliente'));
        $desde = (string) $this->option('desde');
        $pagosDesde = (string) $this->option('pagos-desde');
        $anular = array_map('intval', (array) $this->option('anular'));
        $motivo = trim((string) $this->option('motivo'));
        $dryRun = (bool) $this->option('dry-run');

        if (! $cliente) {
            $this->error('No existe el cliente ' . $this->argument('cliente') . '.');
            return self::FAILURE;
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $desde)) {
            $this->error('--desde debe ser un periodo AAAA-MM.');
            return self::FAILURE;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $pagosDesde)) {
            $this->error('--pagos-desde debe ser una fecha AAAA-MM-DD.');
            return self::FAILURE;
        }
        if ($motivo === '') {
            $this->error('--motivo es obligatorio: queda en la nota de cada factura tocada.');
            return self::FAILURE;
        }
        $montos = $this->leerMontos((array) $this->option('monto-pago'));
        if ($montos === null) {
            return self::FAILURE;
        }

        $plan = $this->planear($cliente, $desde, $pagosDesde, $anular, $montos);
        if ($plan === null) {
            return self::FAILURE;
        }

        $this->imprimirPlan($cliente, $plan, $dryRun);
        if ($dryRun) {
            $this->warn('Simulación: no se escribió nada.');
            return self::SUCCESS;
        }
        if (! $plan['cambios']) {
            $this->info('No hay nada que cambiar.');
            return self::SUCCESS;
        }

        $base = 'respaldos/facturacion/reacomodo-cliente-' . $cliente->id . '-' . now()->format('Ymd-His');
        $respaldo = $this->reacomodo->respaldo($cliente, ['comando' => 'facturacion:reacomodar-pagos', 'parametros' => $this->options() + ['cliente' => $cliente->id]]);
        Storage::disk('local')->put($base . '.json', json_encode($respaldo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $deshacer = DB::transaction(function () use ($cliente, $plan, $motivo) {
            Client::where('id', $cliente->id)->lockForUpdate()->first();

            return $this->reacomodo->aplicar($cliente, $plan, $motivo);
        });

        Storage::disk('local')->put($base . '-deshacer.sql', implode("\n", $deshacer) . "\n");
        Storage::disk('local')->put($base . '-reporte.txt', implode("\n", $this->reporte) . "\n");

        $this->info('Hecho. Respaldo, SQL para deshacer y reporte en storage/app/' . $base . '{.json,-deshacer.sql,-reporte.txt}');
        $this->imprimirPlan($cliente->fresh(), $this->planear($cliente->fresh(), $desde, $pagosDesde, [], []) ?? $plan, false, 'DESPUÉS (leído de la base)');

        return self::SUCCESS;
    }

    /** --monto-pago=PAGO=MONTO → [pago => monto] o null si alguno no se entiende. */
    private function leerMontos(array $valores): ?array
    {
        $montos = [];
        foreach ($valores as $valor) {
            if (! preg_match('/^(\d+)=(\d+(?:\.\d{1,2})?)$/', trim($valor), $m)) {
                $this->error("--monto-pago «{$valor}» debe ser PAGO=MONTO, por ejemplo 135973=349.");
                return null;
            }
            $montos[(int) $m[1]] = round((float) $m[2], 2);
        }

        return $montos;
    }

    /** Plan del servicio; si los datos no cuadran, muestra el motivo y devuelve null. */
    private function planear(Client $cliente, string $desde, string $pagosDesde, array $anular, array $montos): ?array
    {
        try {
            return $this->reacomodo->planear($cliente, $desde, $pagosDesde, $anular, $montos);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return null;
        }
    }

    private function imprimirPlan(Client $cliente, array $plan, bool $dryRun, ?string $titulo = null): void
    {
        $saldo = Balance::where('balanceable_id', $cliente->id)->whereIn('balanceable_type', ReacomodoPagos::TIPOS_CLIENTE)->value('amount');
        $this->linea('');
        $this->linea(($titulo ?? ($dryRun ? 'SIMULACIÓN' : 'PLAN')) . " — cliente {$cliente->id} · fecha de corte {$cliente->fecha_corte} · saldo {$saldo}");

        $tabla = [];
        foreach ($plan['filas'] as $fila) {
            $f = $fila['factura'];
            $tabla[] = [
                $f->id, $f->period, substr((string) $f->due_date, 0, 10), (float) $f->total,
                $f->getOriginal('status') . ' / ' . ($f->getOriginal('payment_id') ?: '—'),
                $fila['despues']['status'] . ' / ' . ($fila['despues']['payment_id'] ?: '—'),
                $fila['pago'] ? substr((string) $fila['pago']->date, 0, 10) . ' por ' . $fila['monto'] : '',
                $fila['sobrante'] ?: '',
                $fila['cambia'] ? 'sí' : '',
            ];
        }
        foreach ($plan['anuladas'] as $f) {
            $tabla[] = [$f->id, $f->period, substr((string) $f->due_date, 0, 10), (float) $f->total, $f->getOriginal('status') . ' / ' . ($f->getOriginal('payment_id') ?: '—'), 'cancelled / —', '', '', 'anula'];
        }
        $encabezado = ['Factura', 'Periodo', 'Vence', 'Total', 'Antes: estado / pago', 'Después: estado / pago', 'Pago (fecha por monto)', 'Sobra', 'Cambia'];
        $this->table($encabezado, $tabla);
        foreach ($tabla as $r) {
            $this->reporte[] = implode(' | ', $r);
        }

        foreach ($plan['sin_factura'] as $p) {
            $this->linea("Pago {$p->id} ({$p->date}, {$p->amount}) queda sin factura: su dinero sigue como saldo a favor.");
        }
        foreach ($plan['ajustes'] as $pagoId => $a) {
            $texto = match ($a['accion']) {
                'corregir_pago' => "el pago pasa de {$a['en_pago']} a {$a['monto']}; el saldo no cambia",
                'corregir_saldo' => "movimiento de corrección por {$a['diferencia']} en el estado de cuenta (decía {$a['en_cuenta']}); el saldo baja en esa cantidad",
                default => 'ya coincide, no se toca',
            };
            $this->linea("Monto real del pago {$pagoId} = {$a['monto']}: {$texto}.");
        }
        foreach ($this->reacomodo->montosPendientes($plan) as $m) {
            $this->linea("PENDIENTE: el pago {$m['pago']} dice " . number_format($m['en_pago'], 2, '.', '') . ' y su estado de cuenta ' . number_format($m['en_cuenta'], 2, '.', '') . ". No se toca hasta confirmar el monto con --monto-pago={$m['pago']}=MONTO.");
        }
    }

    private function linea(string $texto): void
    {
        $this->reporte[] = $texto;
        $this->line($texto);
    }
}
