<?php

namespace App\Services\Finance\Billing;

use App\Models\Balance;
use App\Models\Invoice;
use App\Models\Payment;
use App\Modules\Core\Clientes\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Item #571: reacomodo de los pagos de UN cliente para que cada uno quede en su mes. Toma sus
 * proformas desde un periodo y sus pagos desde una fecha, en orden, y liga el primer pago a la
 * primera proforma, el segundo a la segunda, etc. Lo que sobra de un pago queda como saldo a
 * favor (no se reparte) y una proforma sin pago queda pendiente.
 *
 * No toca el estado de cuenta (transactions) ni el saldo, salvo el ajuste explícito de un monto de
 * pago verificado con el banco. Lo usan facturacion:reacomodar-pagos y las correcciones
 * automáticas (CorreccionesFacturacion); si los datos no cuadran con los parámetros, planear()
 * lanza InvalidArgumentException con el motivo: mejor detenerse que reacomodar a medias.
 */
class ReacomodoPagos
{
    public const TIPOS_CLIENTE = ['App\\Models\\Client', 'App\\Modules\\Core\\Clientes\\Models\\Client'];

    /**
     * Arma el plan sin escribir.
     *
     * @param int[] $anular proformas que se cancelan (no se cobran)
     * @param array<int, float> $montos pago => monto real verificado
     */
    public function planear(Client $cliente, string $desde, string $pagosDesde, array $anular, array $montos): array
    {
        $proformas = Invoice::proforma()
            ->where('client_id', $cliente->id)
            ->where('period', '>=', $desde)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->orderBy('period')->orderBy('id')
            ->get();

        foreach ($anular as $id) {
            if (! $proformas->contains('id', $id)) {
                throw new InvalidArgumentException("La proforma {$id} no es del cliente, ya está cancelada o es anterior a --desde.");
            }
        }
        $anuladas = $proformas->whereIn('id', $anular)->values();
        $proformas = $proformas->whereNotIn('id', $anular)->values();

        foreach ($proformas as $f) {
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $f->period)) {
                throw new InvalidArgumentException("La proforma {$f->id} tiene un periodo «{$f->period}» que no es AAAA-MM.");
            }
        }
        $repetidos = $proformas->groupBy('period')->filter(fn ($g) => $g->count() > 1);
        if ($repetidos->isNotEmpty()) {
            throw new InvalidArgumentException('Hay más de una proforma del mismo periodo: ' . $repetidos->map(fn ($g, $p) => $p . ' (' . $g->pluck('id')->implode(', ') . ')')->implode('; ') . '. Anula la que sobre con --anular.');
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
                throw new InvalidArgumentException("La proforma {$f->id} ({$f->period}) está ligada al pago {$f->payment_id}, que es anterior a --pagos-desde. Ajusta los parámetros.");
            }
        }
        $idsFacturas = $proformas->concat($anuladas)->pluck('id')->all();
        $fuera = Invoice::whereIn('payment_id', $idsPagos)->whereNotIn('id', $idsFacturas)->where('status', '!=', Invoice::STATUS_CANCELLED)->get();
        if ($fuera->isNotEmpty()) {
            throw new InvalidArgumentException('Hay pagos del reacomodo ligados a facturas fuera de él: ' . $fuera->map(fn ($f) => "pago {$f->payment_id} → factura {$f->id} ({$f->period})")->implode('; ') . '. Ajusta --desde.');
        }

        $ajustes = $this->planearMontos($cliente, $montos);

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
     * Qué hacer con cada monto verificado. El monto real debe coincidir con uno de los dos registros:
     * - con el estado de cuenta → se corrige el monto del pago (el saldo ya estaba bien);
     * - con el pago → el estado de cuenta tiene de más/de menos: va un movimiento de corrección
     *   por la diferencia y el saldo se ajusta en esa misma cantidad. Nada se borra.
     */
    public function planearMontos(Client $cliente, array $montos): array
    {
        $ajustes = [];
        foreach ($montos as $pagoId => $real) {
            $pago = Payment::where('id', $pagoId)->where('paymentable_id', $cliente->id)->whereIn('paymentable_type', self::TIPOS_CLIENTE)->first();
            if (! $pago) {
                throw new InvalidArgumentException("El pago {$pagoId} no es del cliente.");
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
                throw new InvalidArgumentException("El pago {$pagoId}: {$real} no coincide con el pago ({$enPago}) ni con el estado de cuenta (" . ($enCuenta ?? 'sin movimiento') . '). Revisa el monto.');
            }
        }

        return $ajustes;
    }

    /** Plan solo de montos (sin reacomodar ligas), para confirmar el monto real de un pago. */
    public function planSoloMontos(Client $cliente, array $montos): array
    {
        $ajustes = $this->planearMontos($cliente, $montos);

        return ['filas' => [], 'anuladas' => collect(), 'sin_factura' => collect(), 'pagos' => collect(), 'ajustes' => $ajustes,
            'cambios' => collect($ajustes)->contains(fn ($a) => $a['accion'] !== 'ninguna')];
    }

    public function movimientoDelPago(int $pagoId): ?object
    {
        return DB::table('transactions')->where('payment_id', $pagoId)->where('is_payment', 1)->whereNull('deleted_at')->orderBy('id')->first();
    }

    /**
     * Pagos del plan cuyo monto no coincide con su estado de cuenta y que el plan no corrige:
     * quedan pendientes hasta confirmar el monto real con el banco.
     *
     * @return array<int, array{pago: int, en_pago: float, en_cuenta: float, fecha: string}>
     */
    public function montosPendientes(array $plan): array
    {
        $pendientes = [];
        foreach ($plan['pagos'] as $p) {
            $mov = $this->movimientoDelPago($p->id);
            if ($mov && abs((float) $mov->credit - (float) $p->amount) > 0.004 && ! isset($plan['ajustes'][$p->id])) {
                $pendientes[$p->id] = ['pago' => $p->id, 'en_pago' => round((float) $p->amount, 2), 'en_cuenta' => round((float) $mov->credit, 2), 'fecha' => substr((string) $p->date, 0, 10)];
            }
        }

        return $pendientes;
    }

    /** Lo que el cliente debe en las proformas del plan, antes y después (canceladas fuera). */
    public function deuda(array $plan): array
    {
        $antes = collect($plan['filas'])->sum(fn ($f) => (float) $f['factura']->getOriginal('pending_balance'))
            + $plan['anuladas']->sum(fn ($f) => (float) $f->getOriginal('pending_balance'));
        $despues = collect($plan['filas'])->sum(fn ($f) => (float) $f['despues']['pending_balance']);

        return [round($antes, 2), round($despues, 2)];
    }

    /** Escribe el plan (dentro de la transacción del llamador). Devuelve el SQL que deja todo como estaba. */
    public function aplicar(Client $cliente, array $plan, string $motivo): array
    {
        $hoy = now()->toDateString();
        $deshacer = ["-- Deshace el reacomodo de pagos del cliente {$cliente->id} ({$hoy}). Revisar antes de correr."];
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

    /** Filas del cliente tal cual están en la base (sin accesores de los modelos). */
    public function respaldo(Client $cliente, array $contexto): array
    {
        return $contexto + [
            'cuando' => now()->toDateTimeString(),
            'cliente' => DB::table('clients')->where('id', $cliente->id)->first(),
            'saldo' => DB::table('balances')->where('balanceable_id', $cliente->id)->whereIn('balanceable_type', self::TIPOS_CLIENTE)->get(),
            'facturas' => DB::table('invoices')->where('client_id', $cliente->id)->get(),
            'pagos' => DB::table('payments')->where('paymentable_id', $cliente->id)->whereIn('paymentable_type', self::TIPOS_CLIENTE)->get(),
            'movimientos' => DB::table('transactions')->where('client_id', $cliente->id)->get(),
        ];
    }
}
