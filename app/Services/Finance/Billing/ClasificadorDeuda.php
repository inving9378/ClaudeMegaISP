<?php

namespace App\Services\Finance\Billing;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Item #571 (fase 3), solo lectura: separa las proformas abiertas de un cliente en deuda real y
 * deuda fantasma. Lo usan facturacion:clasificar-deuda y las correcciones automáticas.
 *
 * Cada proforma abierta queda como:
 * - saldo_cero: abierta pero sin nada pendiente (nació así al cobrar) → fantasma;
 * - duplicada: su periodo ya tiene otra proforma pagada → fantasma;
 * - cubierta: el cliente no debe en su estado de cuenta y su fecha de corte ya va un ciclo
 *   adelante del vencimiento → probable fantasma (como el cliente 1286), se revisa a mano;
 * - vencida / próxima: cargo real, ya vencido o del ciclo que viene.
 */
class ClasificadorDeuda
{
    public const DEBE = 'DEBE';
    public const AL_CORRIENTE = 'AL CORRIENTE';
    public const FANTASMA = 'FANTASMA';
    public const FANTASMA_DEBE = 'FANTASMA + DEBE';
    public const REVISAR = 'REVISAR';

    /** Días que la fecha de corte debe ir por delante del vencimiento para dar el ciclo por pagado. */
    private const DIAS_CICLO_CUBIERTO = 25;

    /**
     * Clientes a revisar: por defecto los que tienen dos o más proformas del mismo vencimiento y
     * alguna abierta (el rastro del error del cobro en caja); con $todos, toda proforma abierta.
     */
    public function poblacion(bool $todos, array $extra = []): Collection
    {
        $abiertas = fn ($q) => $q->whereNull('deleted_at')->where('type', Invoice::TYPE_PROFORMA)
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED]);

        $ids = $todos
            ? $abiertas(DB::table('invoices'))->distinct()->pluck('client_id')
            : DB::table('invoices')->whereNull('deleted_at')->whereNotNull('due_date')
                ->selectRaw('client_id, date(due_date) vence, count(*) n, sum(status not in (?, ?)) abiertas', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
                ->groupBy('client_id', 'vence')->havingRaw('n > 1 and abiertas > 0')
                ->pluck('client_id');

        return $ids->merge(array_map('intval', $extra))->unique()->sort()->values();
    }

    /**
     * @return array{cliente: int, nombre: string, estado: string, fecha_corte: ?string, saldo: float,
     *               categoria: string, detalle: string, propuesta: ?array{desde: string, pagos_desde: string, anular: int[], motivo: string}}
     */
    public function clasificar(int $id, Carbon $hoy): array
    {
        $cmi = DB::table('client_main_information')->where('client_id', $id)->first(['name', 'father_last_name', 'mother_last_name', 'estado']);
        $fechaCorte = DB::table('clients')->where('id', $id)->value('fecha_corte');
        $corte = $this->fecha($fechaCorte);
        $saldo = (float) DB::table('balances')->where('balanceable_id', $id)->whereIn('balanceable_type', ReacomodoPagos::TIPOS_CLIENTE)->value('amount');

        $proformas = DB::table('invoices')->where('client_id', $id)->whereNull('deleted_at')->where('type', Invoice::TYPE_PROFORMA)
            ->orderBy('period')->orderBy('id')->get(['id', 'period', 'due_date', 'status', 'pending_balance', 'payment_id']);
        $pagadas = $proformas->where('status', Invoice::STATUS_PAID)->countBy('period');

        $grupos = ['saldo_cero' => [], 'duplicada' => [], 'cubierta' => [], 'vencida' => [], 'proxima' => []];
        foreach ($proformas->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED]) as $f) {
            $vence = $this->fecha($f->due_date);
            $clave = match (true) {
                (float) $f->pending_balance <= 0 => 'saldo_cero',
                ($pagadas[$f->period] ?? 0) > 0 => 'duplicada',
                $saldo >= -0.5 && $corte && $vence && $vence->diffInDays($corte, false) >= self::DIAS_CICLO_CUBIERTO => 'cubierta',
                $vence && $vence->gte($hoy) => 'proxima',
                default => 'vencida',
            };
            $grupos[$clave][] = $f;
        }

        $fantasma = array_merge($grupos['saldo_cero'], $grupos['duplicada']);
        $categoria = match (true) {
            ! empty($grupos['cubierta']) => self::REVISAR,
            ! empty($fantasma) && ! empty($grupos['vencida']) => self::FANTASMA_DEBE,
            ! empty($fantasma) => self::FANTASMA,
            ! empty($grupos['vencida']) => self::DEBE,
            default => self::AL_CORRIENTE,
        };

        $partes = [];
        $nombres = ['saldo_cero' => 'abierta sin saldo', 'duplicada' => 'duplicada de un periodo pagado', 'cubierta' => 'su ciclo ya está cubierto por la fecha de corte', 'vencida' => 'vencida', 'proxima' => 'aún no vence'];
        foreach ($grupos as $clave => $lista) {
            foreach ($lista as $f) {
                $partes[] = "{$f->id} ({$f->period}, vence " . substr((string) $f->due_date, 0, 10) . ", {$f->pending_balance}): {$nombres[$clave]}";
            }
        }
        $vencido = array_sum(array_map(fn ($f) => (float) $f->pending_balance, $grupos['vencida']));
        if ($saldo < -0.5 && abs($vencido + $saldo) > 1) {
            $partes[] = 'estado de cuenta debe ' . number_format(-$saldo, 2, '.', '') . ', facturas vencidas ' . number_format($vencido, 2, '.', '');
        }
        $borrados = DB::table('payments')->where('paymentable_id', $id)->whereIn('paymentable_type', ReacomodoPagos::TIPOS_CLIENTE)
            ->whereNotNull('deleted_at')->where('date', '>=', $hoy->copy()->subYear())->count();
        if ($borrados) {
            $partes[] = "{$borrados} pago(s) borrado(s) en el último año";
        }

        return [
            'cliente' => $id,
            'nombre' => $cmi ? trim("{$cmi->name} {$cmi->father_last_name} {$cmi->mother_last_name}") : '',
            'estado' => $cmi->estado ?? '',
            'fecha_corte' => $fechaCorte,
            'saldo' => $saldo,
            'categoria' => $categoria,
            'detalle' => implode('; ', $partes),
            'pagos_borrados' => $borrados,
            'propuesta' => $fantasma && $categoria !== self::REVISAR ? $this->propuesta($id, $proformas, $fantasma, $grupos['duplicada']) : null,
        ];
    }

    /**
     * Corrección derivable sola: reacomodar desde el periodo de la primera fantasma, con los pagos
     * ligados a proformas de ahí en adelante, cancelando las duplicadas. ReacomodoPagos vuelve a
     * validar todo; si algo no cuadra, se detiene sin escribir.
     */
    private function propuesta(int $id, Collection $proformas, array $fantasma, array $duplicadas): array
    {
        // El rango de pagos empieza en el primero ligado a una proforma del rango; si algún pago
        // de ahí en adelante pagó una proforma anterior, el rango de proformas también se abre.
        $desde = collect($fantasma)->min('period');
        $tipos = ReacomodoPagos::TIPOS_CLIENTE;
        do {
            $ligados = $proformas->where('period', '>=', $desde)->pluck('payment_id')->filter()->unique();
            $pagosDesde = $ligados->isNotEmpty()
                ? substr((string) DB::table('payments')->whereIn('id', $ligados)->min('date'), 0, 10)
                : substr((string) collect($fantasma)->min('due_date'), 0, 10);
            $enRango = DB::table('payments')->where('paymentable_id', $id)->whereIn('paymentable_type', $tipos)
                ->whereNull('deleted_at')->where('date', '>=', $pagosDesde)->pluck('id');
            $antes = $proformas->where('period', '<', $desde)->whereIn('payment_id', $enRango)->min('period');
            $desde = $antes ?? $desde;
        } while ($antes !== null);

        return [
            'desde' => $desde,
            'pagos_desde' => $pagosDesde,
            'anular' => array_map(fn ($f) => (int) $f->id, $duplicadas),
            'motivo' => 'Item #571: proforma ' . (count($duplicadas) ? 'duplicada cancelada' : 'abierta sin saldo cerrada') . ' y pagos reacomodados a su mes',
        ];
    }

    private function fecha($valor): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $valor) ? Carbon::parse(substr((string) $valor, 0, 10))->startOfDay() : null;
    }
}
