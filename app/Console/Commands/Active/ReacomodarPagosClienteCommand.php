<?php

namespace App\Console\Commands\Active;

use App\Models\Balance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Modules\Core\Clientes\Models\Client;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Item #571 (caso cliente 1286, 2026-10-21): reacomoda los pagos de UN cliente para que cada uno
 * quede en su mes. Toma sus proformas desde --desde y sus pagos desde --pagos-desde, en orden, y
 * liga el primer pago a la primera proforma, el segundo a la segunda, etc. Lo que sobra de un
 * pago queda como saldo a favor (no se reparte), y una proforma sin pago queda pendiente.
 *
 * No toca el estado de cuenta (transactions) ni el saldo, salvo el ajuste explícito de
 * --monto-pago. Antes de escribir guarda un respaldo JSON, el SQL para deshacer y el reporte en
 * storage/app/respaldos/facturacion/. Con --dry-run solo muestra el antes y el después.
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

    private const TIPOS_CLIENTE = ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'];

    /** Líneas del reporte, también se guardan junto al respaldo. */
    private array $reporte = [];

    public function handle(): int
    {
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
        Storage::disk('local')->put($base . '.json', json_encode($this->respaldo($cliente), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $deshacer = DB::transaction(function () use ($cliente, $plan, $motivo) {
            Client::where('id', $cliente->id)->lockForUpdate()->first();

            return $this->aplicar($cliente, $plan, $motivo);
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

    /**
     * Arma el plan sin escribir. Devuelve null (con el motivo en pantalla) si los datos no
     * cuadran con los parámetros: mejor detenerse que reacomodar a medias.
     */
    private function planear(Client $cliente, string $desde, string $pagosDesde, array $anular, array $montos): ?array
    {
        $proformas = Invoice::proforma()
            ->where('client_id', $cliente->id)
            ->where('period', '>=', $desde)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->orderBy('period')->orderBy('id')
            ->get();

        foreach ($anular as $id) {
            if (! $proformas->contains('id', $id)) {
                $this->error("La proforma {$id} no es del cliente, ya está cancelada o es anterior a --desde.");
                return null;
            }
        }
        $anuladas = $proformas->whereIn('id', $anular)->values();
        $proformas = $proformas->whereNotIn('id', $anular)->values();

        foreach ($proformas as $f) {
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $f->period)) {
                $this->error("La proforma {$f->id} tiene un periodo «{$f->period}» que no es AAAA-MM.");
                return null;
            }
        }
        $repetidos = $proformas->groupBy('period')->filter(fn ($g) => $g->count() > 1);
        if ($repetidos->isNotEmpty()) {
            $this->error('Hay más de una proforma del mismo periodo: ' . $repetidos->map(fn ($g, $p) => $p . ' (' . $g->pluck('id')->implode(', ') . ')')->implode('; ') . '. Anula la que sobre con --anular.');
            return null;
        }

        $pagos = Payment::where('paymentable_id', $cliente->id)
            ->whereIn('paymentable_type', self::TIPOS_CLIENTE)
            ->where('date', '>=', $pagosDesde)
            ->orderBy('date')->orderBy('id')
            ->get();

        // Las ligas actuales no pueden salirse del reacomodo, o quedaría una factura sin su pago.
        $idsPagos = $pagos->pluck('id')->all();
        foreach ($proformas->concat($anuladas) as $f) {
            if ($f->payment_id && ! in_array((int) $f->payment_id, $idsPagos, true)) {
                $this->error("La proforma {$f->id} ({$f->period}) está ligada al pago {$f->payment_id}, que es anterior a --pagos-desde. Ajusta los parámetros.");
                return null;
            }
        }
        $idsFacturas = $proformas->concat($anuladas)->pluck('id')->all();
        $fuera = Invoice::whereIn('payment_id', $idsPagos)->whereNotIn('id', $idsFacturas)->where('status', '!=', Invoice::STATUS_CANCELLED)->get();
        if ($fuera->isNotEmpty()) {
            $this->error('Hay pagos del reacomodo ligados a facturas fuera de él: ' . $fuera->map(fn ($f) => "pago {$f->payment_id} → factura {$f->id} ({$f->period})")->implode('; ') . '. Ajusta --desde.');
            return null;
        }

        $ajustes = $this->planearMontos($cliente, $montos);
        if ($ajustes === null) {
            return null;
        }

        $filas = [];
        $cambios = $anuladas->isNotEmpty() || ! empty($ajustes);
        foreach ($proformas as $i => $f) {
            $p = $pagos[$i] ?? null;
            $monto = $p ? ($ajustes[$p->id]['monto'] ?? (float) $p->amount) : 0.0;
            $total = (float) $f->total;
            $despues = $p
                ? ($monto >= $total
                    ? ['status' => Invoice::STATUS_PAID, 'pending_balance' => 0.0]
                    : ['status' => Invoice::STATUS_PARTIALLY_PAID, 'pending_balance' => round($total - $monto, 2)])
                : ['status' => Invoice::STATUS_DRAFT, 'pending_balance' => $total];
            $despues += [
                'payment_id' => $p?->id,
                'payment_date' => $p ? Carbon::parse($p->date)->toDateString() : null,
                'payment_method' => $p?->payment_method_id,
                'transaction_id' => $p ? $this->movimientoDelPago($p->id)?->id : null,
            ];
            $cambia = (int) $f->payment_id !== (int) $despues['payment_id']
                || $f->status !== $despues['status']
                || abs((float) $f->pending_balance - $despues['pending_balance']) > 0.004;
            $cambios = $cambios || $cambia || ($p && $p->payment_period !== $f->period);
            $filas[] = ['factura' => $f, 'pago' => $p, 'monto' => $monto, 'sobrante' => $p ? round(max(0, $monto - $total), 2) : 0.0, 'despues' => $despues, 'cambia' => $cambia];
        }

        return [
            'filas' => $filas,
            'anuladas' => $anuladas,
            'sin_factura' => $pagos->slice($proformas->count())->values(),
            'pagos' => $pagos,
            'ajustes' => $ajustes,
            'cambios' => $cambios,
        ];
    }

    /**
     * Qué hacer con cada --monto-pago. El monto real debe coincidir con uno de los dos registros:
     * - con el estado de cuenta → se corrige el monto del pago (el saldo ya estaba bien);
     * - con el pago → el estado de cuenta tiene de más/de menos: va un movimiento de corrección
     *   por la diferencia y el saldo se ajusta en esa misma cantidad. Nada se borra.
     */
    private function planearMontos(Client $cliente, array $montos): ?array
    {
        $ajustes = [];
        foreach ($montos as $pagoId => $real) {
            $pago = Payment::where('id', $pagoId)->where('paymentable_id', $cliente->id)->whereIn('paymentable_type', self::TIPOS_CLIENTE)->first();
            if (! $pago) {
                $this->error("El pago {$pagoId} no es del cliente.");
                return null;
            }
            $enPago = round((float) $pago->amount, 2);
            $enCuenta = ($mov = $this->movimientoDelPago($pagoId)) ? round((float) $mov->credit, 2) : null;

            if ($real === $enPago && ($enCuenta === null || $enCuenta === $real)) {
                $ajustes[$pagoId] = ['monto' => $real, 'accion' => 'ninguna', 'diferencia' => 0.0, 'en_pago' => $enPago, 'en_cuenta' => $enCuenta];
            } elseif ($enCuenta !== null && $real === $enCuenta) {
                $ajustes[$pagoId] = ['monto' => $real, 'accion' => 'corregir_pago', 'diferencia' => 0.0, 'en_pago' => $enPago, 'en_cuenta' => $enCuenta];
            } elseif ($real === $enPago && $enCuenta !== null) {
                $ajustes[$pagoId] = ['monto' => $real, 'accion' => 'corregir_saldo', 'diferencia' => round($enCuenta - $real, 2), 'en_pago' => $enPago, 'en_cuenta' => $enCuenta];
            } else {
                $this->error("El pago {$pagoId}: {$real} no coincide con el pago ({$enPago}) ni con el estado de cuenta (" . ($enCuenta ?? 'sin movimiento') . '). Revisa el monto.');
                return null;
            }
        }

        return $ajustes;
    }

    private function movimientoDelPago(int $pagoId): ?object
    {
        return DB::table('transactions')->where('payment_id', $pagoId)->where('is_payment', 1)->whereNull('deleted_at')->orderBy('id')->first();
    }

    /** Escribe el plan. Devuelve el SQL que deja todo como estaba. */
    private function aplicar(Client $cliente, array $plan, string $motivo): array
    {
        $hoy = now()->toDateString();
        $deshacer = ["-- Deshace facturacion:reacomodar-pagos del cliente {$cliente->id} ({$hoy}). Revisar antes de correr."];
        $q = fn ($v) => $v === null ? 'NULL' : DB::getPdo()->quote((string) $v);
        $guardarFactura = function (Invoice $f) use (&$deshacer, $q) {
            $o = $f->getOriginal();
            $deshacer[] = sprintf(
                'UPDATE invoices SET payment_id=%s, status=%s, pending_balance=%s, payment_date=%s, payment_method=%s, transaction_id=%s, notes=%s WHERE id=%d;',
                $q($o['payment_id']), $q($o['status']), $q($o['pending_balance']), $q($o['payment_date']), $q($o['payment_method']), $q($o['transaction_id']), $q($o['notes']), $f->id
            );
            $f->save();
        };

        foreach ($plan['anuladas'] as $f) {
            $f->status = Invoice::STATUS_CANCELLED;
            $f->pending_balance = 0;
            $f->payment_id = null;
            $f->payment_date = null;
            $f->transaction_id = null;
            $f->notes = trim($f->notes . "\n[anulada {$hoy}] {$motivo}");
            $guardarFactura($f);
        }

        // Primero se sueltan todas las ligas que cambian y luego se ponen las nuevas: así una
        // factura nunca queda pagada dos veces a la mitad del proceso.
        foreach ($plan['filas'] as $fila) {
            if (! $fila['cambia']) {
                continue;
            }
            $f = $fila['factura'];
            $antes = $f->payment_id ? "pago {$f->payment_id}" : 'sin pago';
            $ahora = $fila['despues']['payment_id'] ? "pago {$fila['despues']['payment_id']}" : 'sin pago';
            foreach ($fila['despues'] as $campo => $valor) {
                $f->{$campo} = $valor;
            }
            $f->notes = trim($f->notes . "\n[reacomodo {$hoy}] antes {$antes}, ahora {$ahora}. {$motivo}");
            $guardarFactura($f);
        }

        foreach ($plan['filas'] as $fila) {
            $p = $fila['pago'];
            if ($p && $p->payment_period !== $fila['factura']->period) {
                $deshacer[] = sprintf('UPDATE payments SET payment_period=%s WHERE id=%d;', $q($p->payment_period), $p->id);
                $p->payment_period = $fila['factura']->period;
                $p->save();
            }
        }

        foreach ($plan['ajustes'] as $pagoId => $a) {
            if ($a['accion'] === 'corregir_pago') {
                $p = Payment::find($pagoId);
                $deshacer[] = sprintf('UPDATE payments SET amount=%s WHERE id=%d;', $q($p->amount), $p->id);
                $p->amount = $a['monto'];
                $p->save();
            } elseif ($a['accion'] === 'corregir_saldo') {
                $balance = Balance::where('balanceable_id', $cliente->id)->whereIn('balanceable_type', self::TIPOS_CLIENTE)->first();
                $nuevo = round((float) ($balance->amount ?? 0) - $a['diferencia'], 2);
                $cargo = $a['diferencia'] > 0;
                $id = DB::table('transactions')->insertGetId([
                    'date' => now(), 'client_id' => $cliente->id, 'cantidad' => '1', 'category' => 'Correccion',
                    'type' => $cargo ? 'debit' : 'credit',
                    'debit' => $cargo ? $a['diferencia'] : null, 'credit' => $cargo ? null : abs($a['diferencia']),
                    'price' => abs($a['diferencia']), 'iva' => 0, 'total' => abs($a['diferencia']),
                    'account_balance' => $nuevo, 'company_balance' => $nuevo,
                    'movement' => ($cargo ? '- ' : '+ ') . number_format(abs($a['diferencia']), 2, '.', ''),
                    'description' => 'Corrección de saldo',
                    'comment' => "El pago {$pagoId} quedó en el estado de cuenta por {$a['en_cuenta']}; el monto real es {$a['monto']}. {$motivo}",
                    'add_to_invoice' => 0, 'is_payment' => 0, 'payment_id' => $pagoId,
                    'transactionable_id' => $cliente->id, 'transactionable_type' => 'App\\Models\\Client',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $deshacer[] = "DELETE FROM transactions WHERE id={$id};";
                if ($balance) {
                    $deshacer[] = sprintf('UPDATE balances SET amount=%s WHERE id=%d;', $q($balance->amount), $balance->id);
                    $balance->amount = $nuevo;
                    $balance->save();
                }
            }
        }

        return $deshacer;
    }

    private function respaldo(Client $cliente): array
    {
        // Filas tal cual están en la base (sin accesores de los modelos).
        return [
            'comando' => 'facturacion:reacomodar-pagos', 'cuando' => now()->toDateTimeString(),
            'parametros' => $this->options() + ['cliente' => $cliente->id],
            'cliente' => DB::table('clients')->where('id', $cliente->id)->first(),
            'saldo' => DB::table('balances')->where('balanceable_id', $cliente->id)->whereIn('balanceable_type', self::TIPOS_CLIENTE)->get(),
            'facturas' => DB::table('invoices')->where('client_id', $cliente->id)->get(),
            'pagos' => DB::table('payments')->where('paymentable_id', $cliente->id)->whereIn('paymentable_type', self::TIPOS_CLIENTE)->get(),
            'movimientos' => DB::table('transactions')->where('client_id', $cliente->id)->get(),
        ];
    }

    private function imprimirPlan(Client $cliente, array $plan, bool $dryRun, ?string $titulo = null): void
    {
        $saldo = Balance::where('balanceable_id', $cliente->id)->whereIn('balanceable_type', self::TIPOS_CLIENTE)->value('amount');
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
        foreach ($plan['pagos'] as $p) {
            $mov = $this->movimientoDelPago($p->id);
            if ($mov && abs((float) $mov->credit - (float) $p->amount) > 0.004 && ! isset($plan['ajustes'][$p->id])) {
                $this->linea("PENDIENTE: el pago {$p->id} dice {$p->amount} y su estado de cuenta {$mov->credit}. No se toca hasta confirmar el monto con --monto-pago={$p->id}=MONTO.");
            }
        }
    }

    private function linea(string $texto): void
    {
        $this->reporte[] = $texto;
        $this->line($texto);
    }
}
