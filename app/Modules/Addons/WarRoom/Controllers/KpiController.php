<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Referrals\ReferralCommission;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KpiController extends Controller
{
    public function show(string $view, ?string $period = null): JsonResponse
    {
        return response()->json($this->raw($view, $period ?? now()->format('Y-m')));
    }

    /** Retorna el array de KPIs sin envolver en JsonResponse (para uso en comandos/jobs). */
    public function raw(string $view, string $period): array
    {
        $previous = Carbon::createFromFormat('Y-m', $period)->subMonth()->format('Y-m');
        return match ($view) {
            'resumen'     => $this->resumenKpis($period, $previous),
            'finanzas'    => $this->finanzasKpis($period, $previous),
            'operaciones' => $this->operacionesKpis($period, $previous),
            'ventas'      => $this->ventasKpis($period, $previous),
            'red'         => $this->redKpis($period, $previous),
            'marketing'   => $this->marketingKpis($period, $previous),
            'talento'     => $this->talentoKpis($period, $previous),
            default       => [],
        };
    }

    // ── Vista: Resumen ───────────────────────────────────────────────────────────

    private function resumenKpis(string $current, string $previous): array
    {
        return [
            'period_current'  => $current,
            'period_previous' => $previous,
            'ingresos'        => [
                'current'  => $this->ingresosDelPeriodo($current),
                'previous' => $this->ingresosDelPeriodo($previous),
            ],
            'clientes_nuevos' => [
                'current'  => $this->clientesNuevosDelPeriodo($current),
                'previous' => $this->clientesNuevosDelPeriodo($previous),
            ],
            'comisiones_embajadores' => [
                'current'  => $this->comisionesDelPeriodo($current),
                'previous' => $this->comisionesDelPeriodo($previous),
            ],
            'por_cobrar' => [
                'amount'     => (float) DB::table('client_invoices')->whereNotIn('estado', self::ESTADOS_PAGADO)->sum('total'),
                'count'      => (int)   DB::table('client_invoices')->whereNotIn('estado', self::ESTADOS_PAGADO)->count(),
                'vencida'    => (float) DB::table('client_invoices')->whereIn('estado', ['Atrasado', 'impagado'])->sum('total'),
                'por_vencer' => (float) DB::table('client_invoices')->whereNotIn('estado', array_merge(self::ESTADOS_PAGADO, ['Atrasado', 'impagado', 'Archivado']))->sum('total'),
            ],
            'tickets_abiertos'     => DB::table('tasks')->whereIn('status', ['ToDo', 'InProgress'])->whereNull('deleted_at')->count(),
            'daily_current'        => $this->ingresosDaily($current),
            'daily_previous'       => $this->ingresosDaily($previous),
            'top_performers'       => $this->topPerformers($current),
            'activity_feed'        => $this->activityFeed(),
            'riesgos_oportunidades' => $this->riesgosOportunidades($current),
        ];
    }

    private function topPerformers(string $period): array
    {
        [$y, $m] = explode('-', $period);
        $rows = DB::table('clients')
            ->join('users', DB::raw('CAST(clients.created_by AS UNSIGNED)'), '=', 'users.id')
            ->whereYear('clients.created_at', $y)
            ->whereMonth('clients.created_at', $m)
            ->whereNull('clients.deleted_at')
            ->select('users.name', DB::raw('COUNT(clients.id) as nuevos_clientes'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('nuevos_clientes')
            ->limit(5)
            ->get();

        $max = $rows->max('nuevos_clientes') ?: 1;
        return $rows->map(fn($r) => [
            'name'           => $r->name,
            'nuevos_clientes' => (int) $r->nuevos_clientes,
            'pct'            => round(($r->nuevos_clientes / $max) * 100),
        ])->values()->toArray();
    }

    private function activityFeed(): array
    {
        $typeMap = [
            'Client'        => ['icon' => 'ti-user-plus',    'label' => 'Cliente nuevo'],
            'ClientInvoice' => ['icon' => 'ti-receipt',      'label' => 'Factura creada'],
            'User'          => ['icon' => 'ti-user',         'label' => 'Usuario'],
            'Task'          => ['icon' => 'ti-ticket',       'label' => 'Ticket'],
        ];

        $rows = DB::table('activity_log as al')
            ->leftJoin('users', 'al.causer_id', '=', 'users.id')
            ->select('al.description', 'al.subject_type', 'al.created_at', 'users.name as causer_name')
            ->orderByDesc('al.created_at')
            ->limit(8)
            ->get();

        return $rows->map(function ($r) use ($typeMap) {
            $shortType = class_basename($r->subject_type ?? '');
            $meta      = $typeMap[$shortType] ?? ['icon' => 'ti-activity', 'label' => $shortType];
            return [
                'icon'        => $meta['icon'],
                'label'       => $meta['label'],
                'description' => ucfirst($r->description ?? ''),
                'causer'      => $r->causer_name ?? '—',
                'ago'         => $r->created_at ? Carbon::parse($r->created_at)->diffForHumans() : '',
            ];
        })->values()->toArray();
    }

    private function riesgosOportunidades(string $period): array
    {
        [$y, $m] = explode('-', $period);
        $items = [];

        // ── Riesgos ────────────────────────────────────────────────────────
        $since45 = now()->subDays(45)->toDateString();
        $pd45    = $this->parsedDate('ci.payment_date');
        $sinPago45 = DB::table('clients as c')
            ->whereNull('c.deleted_at')
            ->whereNotExists(function ($q) use ($since45, $pd45) {
                $q->from('client_invoices as ci')
                  ->whereColumn('ci.client_id', 'c.id')
                  ->whereIn('ci.estado', self::ESTADOS_PAGADO)
                  ->whereRaw("$pd45 >= ?", [$since45]);
            })
            ->count();

        if ($sinPago45 > 0) {
            $items[] = [
                'tipo'    => 'riesgo',
                'icono'   => 'ti-alert-triangle',
                'mensaje' => "{$sinPago45} clientes sin pago en los últimos 45 días",
                'valor'   => $sinPago45,
            ];
        }

        $ticketsViejos = DB::table('tasks')
            ->whereIn('status', ['ToDo', 'InProgress'])
            ->whereNull('deleted_at')
            ->where('created_at', '<=', now()->subDays(5)->format('Y-m-d H:i:s'))
            ->count();

        if ($ticketsViejos > 0) {
            $items[] = [
                'tipo'    => 'riesgo',
                'icono'   => 'ti-clock-exclamation',
                'mensaje' => "{$ticketsViejos} tickets sin resolver con más de 5 días",
                'valor'   => $ticketsViejos,
            ];
        }

        // ── Oportunidades ──────────────────────────────────────────────────
        $clientesNuevosMes = $this->clientesNuevosDelPeriodo($period);
        if ($clientesNuevosMes > 0) {
            $items[] = [
                'tipo'    => 'oportunidad',
                'icono'   => 'ti-users-plus',
                'mensaje' => "{$clientesNuevosMes} clientes nuevos registrados este mes",
                'valor'   => $clientesNuevosMes,
            ];
        }

        $comisiones = $this->comisionesDelPeriodo($period);
        if ($comisiones > 0) {
            $formatted = '$' . number_format($comisiones, 0, '.', ',');
            $items[] = [
                'tipo'    => 'oportunidad',
                'icono'   => 'ti-star',
                'mensaje' => "{$formatted} en comisiones de embajadores generadas",
                'valor'   => $comisiones,
            ];
        }

        return $items;
    }

    // ── Vista: Finanzas ──────────────────────────────────────────────────────────

    private function finanzasKpis(string $current, string $previous): array
    {
        [$cy, $cm] = explode('-', $current);
        [$py, $pm] = explode('-', $previous);

        $pd = $this->parsedDate('payment_date');
        $dd = $this->parsedDate('document_date');

        $mrr = DB::table('client_invoices')
            ->whereIn('estado', self::ESTADOS_PAGADO)
            ->whereRaw("YEAR($pd) = ?", [$cy])
            ->whereRaw("MONTH($pd) = ?", [$cm])
            ->sum('total');

        $mrrPrev = DB::table('client_invoices')
            ->whereIn('estado', self::ESTADOS_PAGADO)
            ->whereRaw("YEAR($pd) = ?", [$py])
            ->whereRaw("MONTH($pd) = ?", [$pm])
            ->sum('total');

        // Tasa de cobro histórica: % del total facturado alguna vez que fue cobrado
        $totalFacturado = (float) DB::table('client_invoices')->sum('total');
        $totalCobrado   = (float) DB::table('client_invoices')->whereIn('estado', self::ESTADOS_PAGADO)->sum('total');
        $tasaCobro      = $totalFacturado > 0 ? round(($totalCobrado / $totalFacturado) * 100, 1) : 0;

        // Cartera vencida: facturas atrasadas o impagadas
        $carteraVencida = DB::table('client_invoices')
            ->whereIn('estado', ['Atrasado', 'impagado'])
            ->select(DB::raw('SUM(total) as monto'), DB::raw('COUNT(*) as facturas'))
            ->first();

        $topDeudores = DB::table('client_invoices')
            ->join('clients', 'client_invoices.client_id', '=', 'clients.id')
            ->join('users', 'clients.user_id', '=', 'users.id')
            ->whereNotIn('client_invoices.estado', self::ESTADOS_PAGADO)
            ->select('users.name', DB::raw('SUM(client_invoices.total) as deuda'), DB::raw('COUNT(*) as facturas'))
            ->groupBy('users.name')
            ->orderByDesc('deuda')
            ->limit(10)
            ->get();

        $cashflowProximo = DB::table('client_invoices')
            ->whereNotIn('estado', self::ESTADOS_PAGADO)
            ->whereRaw("$dd IS NOT NULL")
            ->whereRaw("$dd BETWEEN ? AND ?", [now()->toDateString(), now()->addWeeks(4)->toDateString()])
            ->select(DB::raw("DATE_FORMAT($dd,'%Y-%m-%d') as due_date"), DB::raw('SUM(total) as monto'), DB::raw('COUNT(*) as facturas'))
            ->groupBy(DB::raw("DATE_FORMAT($dd,'%Y-%m-%d')"))
            ->orderBy('due_date')
            ->get();

        $weeklySeriesFin = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (float) DB::table('client_invoices')
                ->whereIn('estado', self::ESTADOS_PAGADO)
                ->whereRaw("YEAR({$this->parsedDate('payment_date')}) = ?", [$y])
                ->whereRaw("MONTH({$this->parsedDate('payment_date')}) = ?", [$m])
                ->whereRaw("DAY({$this->parsedDate('payment_date')}) BETWEEN ? AND ?", [$d1, $d2])
                ->sum('total')
        );

        return [
            'period_current'   => $current,
            'period_previous'  => $previous,
            'mrr'              => ['current' => $mrr, 'previous' => $mrrPrev],
            'tasa_cobro'       => $tasaCobro,
            'cartera_vencida'  => [
                'amount'   => (float) ($carteraVencida->monto ?? 0),
                'facturas' => (int)   ($carteraVencida->facturas ?? 0),
            ],
            'top_deudores'     => $topDeudores,
            'cashflow_proximo' => $cashflowProximo,
            'por_cobrar'       => [
                'amount'     => (float) DB::table('client_invoices')->whereNotIn('estado', self::ESTADOS_PAGADO)->sum('total'),
                'count'      => (int)   DB::table('client_invoices')->whereNotIn('estado', self::ESTADOS_PAGADO)->count(),
                'vencida'    => (float) DB::table('client_invoices')->whereIn('estado', ['Atrasado', 'impagado'])->sum('total'),
                'por_vencer' => (float) DB::table('client_invoices')->whereNotIn('estado', array_merge(self::ESTADOS_PAGADO, ['Atrasado', 'impagado', 'Archivado']))->sum('total'),
            ],
            'weekly_series'    => $weeklySeriesFin,
        ];
    }

    // ── Vista: Operaciones ───────────────────────────────────────────────────────

    private function operacionesKpis(string $current, string $previous): array
    {
        [$cy, $cm] = explode('-', $current);
        [$py, $pm] = explode('-', $previous);

        $ticketsCurrent  = DB::table('tasks')->whereYear('created_at', $cy)->whereMonth('created_at', $cm)->whereNull('deleted_at')->count();
        $ticketsPrevious = DB::table('tasks')->whereYear('created_at', $py)->whereMonth('created_at', $pm)->whereNull('deleted_at')->count();

        // Tickets pendientes (abiertos actualmente, sin filtro de mes)
        $ticketsPendientes = DB::table('tasks')
            ->whereNull('deleted_at')
            ->whereIn('status', ['ToDo', 'InProgress'])
            ->count();

        // No existe snapshot histórico de tickets abiertos en un mes pasado
        $ticketsPendientesPrev = null;

        // Tickets cerrados (Done) en el período
        $ticketsCerrados = DB::table('tasks')
            ->whereYear('updated_at', $cy)
            ->whereMonth('updated_at', $cm)
            ->whereNull('deleted_at')
            ->where('status', 'Done')
            ->count();

        $ticketsCerradosPrev = DB::table('tasks')
            ->whereYear('updated_at', $py)
            ->whereMonth('updated_at', $pm)
            ->whereNull('deleted_at')
            ->where('status', 'Done')
            ->count();

        $byStatus = DB::table('tasks')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byPriority = DB::table('tasks')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->whereNull('deleted_at')
            ->select('priority', DB::raw('COUNT(*) as total'))
            ->groupBy('priority')
            ->pluck('total', 'priority');

        // Tiempo promedio de resolución (horas) — tickets Done del período
        $tiempoPromedio = round((float) DB::table('tasks')
            ->where('status', 'Done')
            ->whereNull('deleted_at')
            ->whereYear('updated_at', $cy)
            ->whereMonth('updated_at', $cm)
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, updated_at) > 0')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)')), 1);

        $tiempoPromedioPrev = round((float) DB::table('tasks')
            ->where('status', 'Done')
            ->whereNull('deleted_at')
            ->whereYear('updated_at', $py)
            ->whereMonth('updated_at', $pm)
            ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, updated_at) > 0')
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)')), 1);

        $weeklySeriesOp = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (int) DB::table('tasks')
                ->where('status', 'Done')
                ->whereNull('deleted_at')
                ->whereYear('updated_at', $y)
                ->whereMonth('updated_at', $m)
                ->whereRaw('DAY(updated_at) BETWEEN ? AND ?', [$d1, $d2])
                ->count()
        );

        return [
            'period_current'         => $current,
            'period_previous'        => $previous,
            'tickets'                => ['current' => $ticketsCurrent,  'previous' => $ticketsPrevious],
            'tickets_pendientes'     => ['current' => $ticketsPendientes, 'previous' => $ticketsPendientesPrev],
            'tickets_cerrados'       => ['current' => $ticketsCerrados,   'previous' => $ticketsCerradosPrev],
            'tiempo_promedio'        => ['current' => $tiempoPromedio,    'previous' => $tiempoPromedioPrev],
            'by_status'              => $byStatus,
            'by_priority'            => $byPriority,
            'weekly_series'          => $weeklySeriesOp,
        ];
    }

    // ── Vista: Ventas ────────────────────────────────────────────────────────────

    private function ventasKpis(string $current, string $previous): array
    {
        [$cy, $cm] = explode('-', $current);

        $clientesNuevos = $this->clientesNuevosDelPeriodo($current);
        $comisiones     = $this->comisionesDelPeriodo($current);

        $embajadoresActivos = DB::table('referral_commissions')
            ->where('status', '!=', 'cancelled')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->distinct('beneficiary_id')
            ->count('beneficiary_id');

        $referidosDelMes = DB::table('referrals')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->count();

        $weeklySeriesVen = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (int) DB::table('clients')
                ->whereNull('deleted_at')
                ->whereYear('created_at', $y)
                ->whereMonth('created_at', $m)
                ->whereRaw('DAY(created_at) BETWEEN ? AND ?', [$d1, $d2])
                ->count()
        );

        return [
            'period_current'       => $current,
            'period_previous'      => $previous,
            'clientes_nuevos'      => ['current' => $clientesNuevos, 'previous' => $this->clientesNuevosDelPeriodo($previous)],
            'comisiones'           => ['current' => $comisiones, 'previous' => $this->comisionesDelPeriodo($previous)],
            'embajadores_activos'  => $embajadoresActivos,
            'referidos_del_mes'    => $referidosDelMes,
            'weekly_series'        => $weeklySeriesVen,
        ];
    }

    // ── Vista: Red ───────────────────────────────────────────────────────────────

    private function redKpis(string $current, string $previous): array
    {
        [$cy, $cm] = explode('-', $current);

        // ONUs por estado (datos reales de OLT)
        $onuStats = DB::table('olt_onus')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $onusOnline  = (int) ($onuStats['Online']       ?? 0);
        $onusOffline = (int) ($onuStats['Offline']      ?? 0) +
                       (int) ($onuStats['Power fail']   ?? 0) +
                       (int) ($onuStats['LOS']          ?? 0);
        $onusTotal   = DB::table('olt_onus')->count();

        // OLTs activas
        $oltsActivas = DB::table('olts')->where('status', 'active')->count();
        $oltsTotal   = DB::table('olts')->count();

        // PPPoE configurados (cuentas en mikrotik)
        $ppoeActivos = DB::table('mikrotik_client_ppoes')->count();

        // Uso real por OLT desde puertos PON
        $oltUso = DB::table('olt_pon_ports as p')
            ->join('olts as o', 'p.olt_id', '=', 'o.id')
            ->select(
                'o.name as olt_name',
                DB::raw('SUM(CAST(p.online_onus_count AS UNSIGNED)) as online_onus'),
                DB::raw('SUM(CAST(p.onus_count AS UNSIGNED)) as total_onus'),
                DB::raw('COUNT(p.id) as total_ports')
            )
            ->groupBy('o.id', 'o.name')
            ->orderBy('o.name')
            ->get()
            ->map(fn ($r) => [
                'olt_name'    => $r->olt_name,
                'online_onus' => (int) $r->online_onus,
                'total_onus'  => (int) $r->total_onus,
                'total_ports' => (int) $r->total_ports,
                'pct_up'      => $r->total_onus > 0 ? round(($r->online_onus / $r->total_onus) * 100, 1) : 0,
            ]);

        // Tickets de red (búsqueda por keywords — hasta que tickets tengan categoría propia)
        $ticketsSinInternet = DB::table('tasks')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('title', 'like', '%sin internet%')
                  ->orWhere('title', 'like', '%sin servicio%')
                  ->orWhere('title', 'like', '%sin señal%')
                  ->orWhere('title', 'like', '%sin conexion%')
                  ->orWhere('title', 'like', '%corte%')
                  ->orWhere('description', 'like', '%sin internet%');
            })
            ->count();

        $weeklySeriesRed = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (int) DB::table('tasks')
                ->whereNull('deleted_at')
                ->whereYear('created_at', $y)
                ->whereMonth('created_at', $m)
                ->whereRaw('DAY(created_at) BETWEEN ? AND ?', [$d1, $d2])
                ->where(fn ($q) => $q
                    ->where('title', 'like', '%sin internet%')
                    ->orWhere('title', 'like', '%sin servicio%')
                    ->orWhere('description', 'like', '%sin internet%'))
                ->count()
        );

        return [
            'period_current'       => $current,
            'period_previous'      => $previous,
            'clientes_activos'     => DB::table('clients')->whereNull('deleted_at')->count(),
            'tickets_sin_internet' => $ticketsSinInternet,
            'ppoe_activos'         => $ppoeActivos,
            'olt_uso'              => $oltUso,
            'onus'                 => [
                'total'   => $onusTotal,
                'online'  => $onusOnline,
                'offline' => $onusOffline,
                'pct_up'  => $onusTotal > 0 ? round(($onusOnline / $onusTotal) * 100, 1) : 0,
            ],
            'olts'                 => [
                'activas' => $oltsActivas,
                'total'   => $oltsTotal,
            ],
            'weekly_series'        => $weeklySeriesRed,
        ];
    }

    // ── Vista: Marketing ────────────────────────────────────────────────────────

    private function marketingKpis(string $current, string $previous): array
    {
        [$cy, $cm] = explode('-', $current);
        [$py, $pm] = explode('-', $previous);

        // Publicaciones enviadas (marketing_publications, no marketing_messages que son IA chat)
        $publicacionesActual  = DB::table('marketing_publications')
            ->where('status', 'published')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->count();
        $publicacionesAnterior = DB::table('marketing_publications')
            ->where('status', 'published')
            ->whereYear('created_at', $py)
            ->whereMonth('created_at', $pm)
            ->count();

        $campanias = DB::table('marketing_campaigns')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count();

        // Leads
        $leadsCaptados = DB::table('marketing_leads')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->count();
        $leadsGanados  = DB::table('marketing_leads')
            ->whereYear('created_at', $cy)
            ->whereMonth('created_at', $cm)
            ->where('status', 'won')
            ->count();

        // Desglose por canal (canales configurados con conteo real de publicaciones del mes)
        $canalDesglose = DB::table('marketing_channels as ch')
            ->leftJoin('marketing_publications as p', function ($join) use ($cy, $cm) {
                $join->on('p.channel_id', '=', 'ch.id')
                    ->where('p.status', 'published')
                    ->whereYear('p.created_at', $cy)
                    ->whereMonth('p.created_at', $cm)
                    ->whereNull('p.deleted_at');
            })
            ->where('ch.active', true)
            ->whereNull('ch.deleted_at')
            ->select('ch.name', 'ch.code', DB::raw('COUNT(p.id) as publicaciones'))
            ->groupBy('ch.id', 'ch.name', 'ch.code')
            ->orderByDesc('publicaciones')
            ->get();

        $weeklySeriesMkt = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (int) DB::table('marketing_leads')
                ->whereYear('created_at', $y)
                ->whereMonth('created_at', $m)
                ->whereRaw('DAY(created_at) BETWEEN ? AND ?', [$d1, $d2])
                ->count()
        );

        return [
            'period_current'        => $current,
            'period_previous'       => $previous,
            'mensajes_enviados'     => [
                'current'  => $publicacionesActual,
                'previous' => $publicacionesAnterior,
            ],
            'campanias_activas'     => $campanias,
            'leads_captados'        => $leadsCaptados,
            'leads_ganados'         => $leadsGanados,
            'canal_desglose'        => $canalDesglose,
            'weekly_series'         => $weeklySeriesMkt,
        ];
    }

    // ── Helpers compartidos ──────────────────────────────────────────────────────

    /**
     * Expresión SQL que parsea payment_date / document_date con formato MIXTO.
     * - DD/MM/YYYY  (histórico hasta May-2024) detectado por REGEXP
     * - YYYY-MM-DD  (sistema actual Jun-2024+)
     * NULL / "0" / vacío / malformado → NULL (compatible con NO_ZERO_DATE strict mode).
     */
    private function parsedDate(string $col = 'payment_date'): string
    {
        return "CASE
            WHEN {$col} REGEXP '^[0-3][0-9]/[01][0-9]/[0-9]{4}\$'
                THEN STR_TO_DATE({$col},'%d/%m/%Y')
            WHEN {$col} REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}'
                THEN CAST({$col} AS DATE)
            ELSE NULL
        END";
    }

    private const ESTADOS_PAGADO = ['Pagado', 'Pagar (del saldo de la cuenta)'];

    private function ingresosDelPeriodo(string $period): float
    {
        [$y, $m] = explode('-', $period);
        $pd = $this->parsedDate('payment_date');
        return (float) DB::table('client_invoices')
            ->whereIn('estado', self::ESTADOS_PAGADO)
            ->whereRaw("YEAR($pd) = ?", [$y])
            ->whereRaw("MONTH($pd) = ?", [$m])
            ->sum('total');
    }

    private function clientesNuevosDelPeriodo(string $period): int
    {
        [$y, $m] = explode('-', $period);
        return DB::table('clients')
            ->whereYear('created_at', $y)
            ->whereMonth('created_at', $m)
            ->whereNull('deleted_at')
            ->count();
    }

    private function comisionesDelPeriodo(string $period): float
    {
        [$y, $m] = explode('-', $period);
        return (float) DB::table('referral_commissions')
            ->where('status', '!=', 'cancelled')
            ->whereYear('created_at', $y)
            ->whereMonth('created_at', $m)
            ->sum('commission_amount');
    }

    // ── Vista: Talento (panel resumen para War Room) ─────────────────────────────

    private function talentoKpis(string $current, string $previous): array
    {
        // Guard: if Talento tables don't exist yet, return placeholder
        if (!Schema::hasTable('talento_colaboradores')) {
            return ['available' => false];
        }

        [$cy, $cm] = explode('-', $current);
        $today = now()->toDateString();

        $activeColabs = TalentoColaborador::where('status', 'active')->count();

        $checkedInToday = TalentoAttendance::whereDate('check_in_at', $today)
            ->whereNull('check_out_at')
            ->count();

        $ordersToday = TalentoWorkOrder::whereDate('created_at', $today)->count();
        $ordersValidatedToday = TalentoWorkOrder::whereDate('validated_at', $today)
            ->where('status', 'validated')
            ->count();

        // Alerts: credentials
        $credAlerts = 0;
        if (Schema::hasTable('talento_credentials')) {
            $credAlerts = DB::table('talento_credentials')
                ->whereIn('status', ['expiring', 'expired'])
                ->count();
        }

        // Alerts: deviations unnotified
        $desvioAlerts = 0;
        if (Schema::hasTable('talento_route_deviations')) {
            $desvioAlerts = DB::table('talento_route_deviations')
                ->where('supervisor_notified', false)
                ->count();
        }

        // Top performers (by validated units this month)
        $topPerformers = [];
        if (Schema::hasTable('talento_work_orders')) {
            $topPerformers = DB::table('talento_work_orders as wo')
                ->join('talento_colaboradores as tc', 'wo.colaborador_id', 'tc.id')
                ->join('users', 'tc.user_id', 'users.id')
                ->where('wo.status', 'validated')
                ->whereYear('wo.validated_at', $cy)
                ->whereMonth('wo.validated_at', $cm)
                ->select('users.name', DB::raw('SUM(wo.points) as total_units'))
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_units')
                ->limit(5)
                ->get()
                ->toArray();
        }

        $weeklySeriesTalento = $this->weeklySeriesHelper(
            $current,
            $previous,
            fn ($y, $m, $d1, $d2) => (int) DB::table('talento_work_orders')
                ->where('status', 'validated')
                ->whereYear('validated_at', $y)
                ->whereMonth('validated_at', $m)
                ->whereRaw('DAY(validated_at) BETWEEN ? AND ?', [$d1, $d2])
                ->count()
        );

        return [
            'available'           => true,
            'period_current'      => $current,
            'active_colaboradores'=> $activeColabs,
            'checked_in_today'    => $checkedInToday,
            'orders_today'        => $ordersToday,
            'validated_today'     => $ordersValidatedToday,
            'alerts'              => ['credentials' => $credAlerts, 'desvios' => $desvioAlerts],
            'top_performers'      => $topPerformers,
            'weekly_series'       => $weeklySeriesTalento,
        ];
    }

    private function ingresosDaily(string $period): array
    {
        [$y, $m] = explode('-', $period);
        $pd = $this->parsedDate('payment_date');
        $rows = DB::table('client_invoices')
            ->whereIn('estado', self::ESTADOS_PAGADO)
            ->whereRaw("YEAR($pd) = ?", [$y])
            ->whereRaw("MONTH($pd) = ?", [$m])
            ->select(DB::raw("DAY($pd) as day"), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw("DAY($pd)"))
            ->orderBy('day')
            ->get();

        $days = array_fill(1, 31, 0);
        foreach ($rows as $row) {
            $days[(int)$row->day] = (float)$row->total;
        }
        return $days;
    }

    /**
     * Construye 3 series semanales (mes actual, mes anterior, hace 2 meses) × 4 semanas.
     * Sem 1 = días 1-7, Sem 2 = 8-14, Sem 3 = 15-21, Sem 4 = 22-fin de mes.
     */
    private function weeklySeriesHelper(string $current, string $prev1, callable $aggregator): array
    {
        $prev2   = Carbon::createFromFormat('Y-m', $current)->subMonths(2)->format('Y-m');
        $series  = [];
        foreach ([$current, $prev1, $prev2] as $period) {
            [$y, $m] = explode('-', $period);
            $data = [];
            for ($week = 1; $week <= 4; $week++) {
                $d1     = ($week - 1) * 7 + 1;
                $d2     = $week < 4 ? $week * 7 : 31;
                $data[] = $aggregator($y, $m, $d1, $d2);
            }
            $series[] = ['period' => $period, 'data' => $data];
        }
        return ['labels' => ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'], 'series' => $series];
    }
}
