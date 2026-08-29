<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Fuentes;

use App\Models\Balance;
use App\Models\Client;
use App\Models\GeneralAccountingIncome;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\FuenteRegistry;
use App\Modules\Addons\Payments\Models\ReconciliationTicket;
use App\Services\Cobranza\InvoiceAgingService;

/**
 * Fase 1.1 (item #728) — fuentes vivas de finanzas para el Apartado IV.
 *
 * Cada fuente es de solo lectura: consulta el modelo/servicio existente del
 * módulo dueño de los datos, nunca escribe. `$empresaId` no se usa aquí:
 * ninguna tabla de negocio (clients/invoices/suppliers/...) tiene columna
 * `empresa_id` — Meganet opera hoy como una sola empresa corporativa (ver
 * `EmpresaContextService`), así que filtrar por ese id no aplica todavía.
 *
 * `finanzas.creditos` NO se registra a propósito: no existe ninguna tabla de
 * créditos otorgados/recibidos en el sistema. El concepto cae solo a
 * `PendienteResolver` (ya es el comportamiento correcto sin registrar nada).
 */
class FinanzasFuentes
{
    public static function registrar(FuenteRegistry $registry): void
    {
        $registry->registrar('clientes.cartera', static fn (array $config, int $empresaId): array => self::cartera());
        $registry->registrar('finanzas.saldos_pendientes', static fn (array $config, int $empresaId): array => self::saldosPendientes());
        $registry->registrar('finanzas.antiguedad_saldos', static fn (array $config, int $empresaId): array => self::antiguedadSaldos());
        $registry->registrar('finanzas.proveedores', static fn (array $config, int $empresaId): array => self::proveedores($config));
        $registry->registrar('finanzas.cuentas_por_pagar', static fn (array $config, int $empresaId): array => self::cuentasPorPagar());
        $registry->registrar('finanzas.ingresos_por_periodo', static fn (array $config, int $empresaId): array => self::ingresosPorPeriodo());
        $registry->registrar('finanzas.conciliaciones', static fn (array $config, int $empresaId): array => self::conciliaciones());
    }

    /**
     * Cartera de clientes, SIEMPRE agregada (nunca nominal — el detalle por
     * cliente es un concepto aparte, gateado por permiso de descarga, fuera
     * de esta fase). `balances.amount` negativo = deuda (convención del
     * propio módulo de Clientes, ver `ClientRepository::rectifyBalance`).
     */
    private static function cartera(): array
    {
        $rangos = [
            ['label' => '$0.01 – $500.00', 'min' => 0.01, 'max' => 500.0],
            ['label' => '$500.01 – $1,000.00', 'min' => 500.01, 'max' => 1000.0],
            ['label' => '$1,000.01 – $5,000.00', 'min' => 1000.01, 'max' => 5000.0],
            ['label' => 'Más de $5,000.00', 'min' => 5000.01, 'max' => null],
        ];

        $base = Balance::query()
            ->where('balanceable_type', Client::class)
            ->where('amount', '<', 0);

        $datos = [];
        foreach ($rangos as $rango) {
            $query = (clone $base)->where('amount', '<=', -$rango['min']);
            if ($rango['max'] !== null) {
                $query->where('amount', '>', -$rango['max']);
            }

            $datos[] = [
                'rango'    => $rango['label'],
                'clientes' => (clone $query)->count(),
                'monto'    => round(abs((float) (clone $query)->sum('amount')), 2),
            ];
        }

        return [
            'datos' => $datos,
            'metricas' => [
                'total_clientes_con_saldo' => (clone $base)->count(),
                'total_cartera_vencida'    => round(abs((float) (clone $base)->sum('amount')), 2),
            ],
        ];
    }

    /**
     * Saldos pendientes de cobro: dos orígenes distintos y complementarios —
     * el balance de cuenta del cliente y el saldo pendiente por factura.
     */
    private static function saldosPendientes(): array
    {
        $saldoCuenta = Balance::query()
            ->where('balanceable_type', Client::class)
            ->where('amount', '<', 0);

        $facturas = Invoice::query()->where('pending_balance', '>', 0);

        $datos = [
            [
                'concepto'  => 'Saldo de cuenta de clientes',
                'registros' => (clone $saldoCuenta)->count(),
                'monto'     => round(abs((float) (clone $saldoCuenta)->sum('amount')), 2),
            ],
            [
                'concepto'  => 'Facturas con saldo pendiente',
                'registros' => (clone $facturas)->count(),
                'monto'     => round((float) (clone $facturas)->sum('pending_balance'), 2),
            ],
        ];

        return [
            'datos' => $datos,
            'metricas' => [
                'total_registros' => $datos[0]['registros'] + $datos[1]['registros'],
                'monto_total'     => round($datos[0]['monto'] + $datos[1]['monto'], 2),
            ],
        ];
    }

    /**
     * Antigüedad de saldos: usa `InvoiceAgingService` TAL CUAL (punto único
     * de verdad del bucketing 0-30/31-60/61-90/91+ días), sin reimplementar.
     */
    private static function antiguedadSaldos(): array
    {
        $metricas = app(InvoiceAgingService::class)->metricas();

        $datos = [];
        foreach ($metricas['buckets_antiguedad'] ?? [] as $rango => $bucket) {
            $datos[] = [
                'rango'    => $rango,
                'facturas' => $bucket['facturas'] ?? 0,
                'monto'    => round((float) ($bucket['monto'] ?? 0), 2),
            ];
        }
        unset($metricas['buckets_antiguedad']);

        return [
            'datos'    => $datos,
            'metricas' => $metricas,
        ];
    }

    /**
     * Relación de proveedores. `suppliers` no tiene columna de clasificación
     * todavía: si el concepto pide `config.clasificacion`, no hay forma
     * honesta de filtrar, así que se devuelve vacío con el motivo (nunca se
     * finge un filtro). Sin clasificación en el config → todos.
     */
    private static function proveedores(array $config): array
    {
        if (! empty($config['clasificacion'])) {
            return [
                'datos'    => [],
                'metricas' => ['total' => 0, 'clasificacion' => $config['clasificacion']],
                'mensaje'  => 'Los proveedores todavía no tienen clasificación por categoría en el '
                    . 'catálogo (columna pendiente). Este concepto se llenará cuando exista esa clasificación.',
            ];
        }

        $proveedores = Supplier::query()->orderBy('name')->get();

        $datos = $proveedores->map(fn (Supplier $s) => [
            'nombre'   => $s->name,
            'rfc'      => $s->rfc,
            'telefono' => $s->phone,
            'email'    => $s->email,
            'estado'   => $s->status_name,
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_proveedores' => $proveedores->count(),
                'activos'           => $proveedores->where('status', 'active')->count(),
            ],
        ];
    }

    /**
     * Obligaciones pendientes de pago (facturas de proveedor). El modelo no
     * distingue "pagada"/"pendiente" (su `status` es de flujo de recepción de
     * inventario: pending/dispatched/received/cancelled/denied) — se listan
     * todas con su estado real, sin inventar un criterio de pago que no existe.
     */
    private static function cuentasPorPagar(): array
    {
        $facturas = SupplierInvoice::with('supplier')->orderByDesc('date')->get();

        $datos = $facturas->map(fn (SupplierInvoice $f) => [
            'proveedor' => $f->supplier?->name ?? 'Sin proveedor',
            'folio'     => $f->invoice_number,
            'fecha'     => optional($f->date)->format('d/m/Y'),
            'total'     => round((float) $f->total, 2),
            'estado'    => $f->status_name,
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_facturas' => $facturas->count(),
                'monto_total'    => round((float) $facturas->sum('total'), 2),
            ],
        ];
    }

    /**
     * Ingresos por periodo (últimos 12 meses), agrupados por mes sobre
     * `general_accounting_incomes.created_at` — mismo campo que ya usa
     * `GeneralAccountingController` para sus gráficas, para no divergir.
     */
    private static function ingresosPorPeriodo(): array
    {
        $desde = now()->copy()->subMonths(11)->startOfMonth();

        $filas = GeneralAccountingIncome::query()
            ->where('created_at', '>=', $desde)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as periodo, SUM(amount) as monto, COUNT(*) as ingresos")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get();

        $datos = $filas->map(fn ($f) => [
            'periodo'  => $f->periodo,
            'ingresos' => (int) $f->ingresos,
            'monto'    => round((float) $f->monto, 2),
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_periodo'    => round((float) $filas->sum('monto'), 2),
                'promedio_mensual' => $filas->count() > 0 ? round((float) $filas->avg('monto'), 2) : 0,
            ],
        ];
    }

    /**
     * Conciliaciones financieras, agrupadas por estado. `reconciliation_tickets`
     * no tiene columna `archived_at` ni estado "archivado" (verificado); usa
     * SoftDeletes, y el scope global de Eloquent ya excluye los soft-deleted
     * de esta consulta sin necesidad de filtro extra.
     */
    private static function conciliaciones(): array
    {
        $porEstado = ReconciliationTicket::query()
            ->selectRaw('status, COUNT(*) as total, SUM(amount) as monto')
            ->groupBy('status')
            ->get();

        $datos = $porEstado->map(fn ($t) => [
            'estado'  => $t->status,
            'tickets' => (int) $t->total,
            'monto'   => round((float) $t->monto, 2),
        ])->all();

        return [
            'datos' => $datos,
            'metricas' => [
                'total_tickets' => (int) $porEstado->sum('total'),
                'abiertos'      => ReconciliationTicket::query()->where('status', 'open')->count(),
            ],
        ];
    }
}
