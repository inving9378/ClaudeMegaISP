<?php

namespace App\Modules\Addons\Talento\Console;

use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoComisionEspejo;
use App\Modules\Addons\Talento\Models\TalentoReconciliacionLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fase 1c (item #9990611) del puente Vendedores→Talento (docs/talento-comisiones-migracion-
 * analisis-item-9990453.md §4.3). Depende de la Fase 1a (#9990609, ya mergeada: tabla
 * talento_comisiones_espejo) y la Fase 1b (#9990610, observer que la puebla -- puede seguir
 * sin mergear: mientras TALENTO_VENDEDORES_ESPEJO_ENABLED siga en false la espejo tiene 0
 * filas y el reporte lo dice explícitamente, es un resultado válido).
 *
 * 100% SOLO LECTURA -- no escribe en payment_by_rule ni en talento_comisiones_espejo, no
 * corrige nada (decisión Irving, q1: reportar, nunca auto-corregir). Compara, por vendedor y
 * ventana ORIGINAL Domingo-Sábado (source_period_start/source_period_end -- la que escribe
 * CalculateBalanceSellerService, NO la ya remapeada a PayWeek por la Fase 1b), la suma del
 * motor viejo (payment_by_rule_commissions vía payment_by_rule) contra la suma del espejo
 * (talento_comisiones_espejo). Comparar en la ventana original, antes del remapeo de
 * calendario, aísla bugs de copiado/replicación de los que introduciría el mapeo a PayWeek.
 *
 * NUNCA falla ni bloquea nada si hay diferencias (decisión Irving: reportar, no abortar) --
 * el comando siempre termina en éxito. Las diferencias se reportan a un log de archivo
 * dedicado (canal `talento_reconciliacion`, mismo patrón que backup/pagos_recurrentes) y se
 * registran en `talento_reconciliacion_log` (decisión Irving, q3) para auditoría histórica.
 */
class ReconciliarComisionesEspejoCommand extends Command
{
    protected $signature = 'talento:reconciliar-comisiones-espejo
        {--periodo= : Fecha Y-m-d del inicio de la semana Domingo-Sábado (source_period_start) a conciliar. Default: todas las encontradas en cualquiera de los dos lados.}';

    protected $description = 'SOLO LECTURA — Fase 1c #9990611: concilia payment_by_rule (motor viejo) contra '
        . 'talento_comisiones_espejo (espejo Fase 1a/1b #9990609/#9990610), por vendedor y ventana original.';

    /** @var array<int, array{colaborador_id:int,nombre:string}|null> cache seller_id → colaborador resuelto */
    private array $colaboradorPorSeller = [];

    public function handle(): int
    {
        $periodo = $this->option('periodo');

        $this->info($periodo
            ? "Conciliando comisiones espejo de la semana que inicia {$periodo}…"
            : 'Conciliando comisiones espejo de TODAS las semanas encontradas…');
        $this->line('(Solo lectura — no se escribe nada en payment_by_rule ni en talento_comisiones_espejo.)');
        $this->newLine();

        [$vendedores, $sinColaborador] = $this->sumarMotorViejo($periodo);
        $espejo                        = $this->sumarEspejo($periodo);

        $rows = $this->construirDiff($vendedores, $espejo);

        $discrepancias = array_filter($rows, fn ($r) => $r['estado'] !== 'OK');

        $this->table(
            ['Seller', 'Colaborador', 'Período (origen)', 'Vendedores', 'Espejo Talento', 'Diferencia', 'Estado'],
            array_map(fn ($r) => [
                $r['seller_id'],
                $r['nombre'],
                $r['periodo'],
                number_format($r['monto_vendedores'], 2),
                number_format($r['monto_espejo'], 2),
                number_format($r['diferencia'], 2),
                $r['estado'],
            ], $rows)
        );

        if ($sinColaborador) {
            $this->newLine();
            $this->warn('Vendedores del motor viejo SIN colaborador de Talento vinculado (no se pueden conciliar):');
            foreach ($sinColaborador as $sellerId => $monto) {
                $this->line("  - seller_id={$sellerId} → suma sin conciliar: " . number_format($monto, 2));
            }
        }

        $this->registrarAuditoria($discrepancias);
        $this->registrarLog($periodo, count($rows), $discrepancias, $sinColaborador);

        $this->newLine();
        $this->info(empty($rows)
            ? 'Sin datos que conciliar todavía (espejo vacío y/o motor viejo vacío en el rango pedido).'
            : (empty($discrepancias)
                ? 'Sin discrepancias — motor viejo y espejo cuadran al centavo.'
                : 'Discrepancias encontradas: ' . count($discrepancias) . ' (ver log ' . storage_path('logs/talento-reconciliacion.log') . ').'));

        // Decisión Irving: reportar, nunca bloquear — este comando SIEMPRE termina en éxito.
        return self::SUCCESS;
    }

    /**
     * Motor viejo: payment_by_rule_commissions (detalle, start_date/end_date = ventana
     * Domingo-Sábado real ya escrita por CalculateBalanceSellerService) + payment_by_rule
     * (header, trae seller_id).
     *
     * @return array{0:array<string,array{amount:float,nombre:string,seller_id:int,period_start:string,period_end:string}>,1:array<int,float>}
     */
    private function sumarMotorViejo(?string $periodo): array
    {
        $rows = DB::table('payment_by_rule_commissions as d')
            ->join('payment_by_rule as p', 'p.id', '=', 'd.payment_id')
            ->when($periodo, fn ($q) => $q->whereDate('d.start_date', $periodo))
            ->select('d.amount', 'p.seller_id', 'd.start_date', 'd.end_date')
            ->get();

        $out            = [];
        $sinColaborador = [];

        foreach ($rows as $row) {
            $colaborador = $this->resolveColaborador((int) $row->seller_id);
            $key         = "{$row->seller_id}|{$row->start_date}|{$row->end_date}";

            $out[$key] ??= [
                'amount'       => 0.0,
                'nombre'       => $colaborador['nombre'] ?? "seller #{$row->seller_id} (sin colaborador)",
                'seller_id'    => (int) $row->seller_id,
                'period_start' => $row->start_date,
                'period_end'   => $row->end_date,
            ];
            $out[$key]['amount'] += (float) $row->amount;

            if ($colaborador === null) {
                $sinColaborador[$row->seller_id] = ($sinColaborador[$row->seller_id] ?? 0.0) + (float) $row->amount;
            }
        }

        return [$out, $sinColaborador];
    }

    /**
     * Espejo: talento_comisiones_espejo, agrupado por seller_id + source_period_start/end
     * (la ventana ORIGINAL, no la ya mapeada a PayWeek por la Fase 1b).
     *
     * @return array<string,array{amount:float,nombre:string,seller_id:int,period_start:string,period_end:string}>
     */
    private function sumarEspejo(?string $periodo): array
    {
        $entries = TalentoComisionEspejo::query()
            ->when($periodo, fn ($q) => $q->whereDate('source_period_start', $periodo))
            ->get();

        $colaboradorIds = $entries->pluck('colaborador_id')->unique();
        $nombres = TalentoColaborador::with('user')
            ->whereIn('id', $colaboradorIds)
            ->get()
            ->keyBy('id')
            ->map(fn (TalentoColaborador $c) => $c->user?->name ?? "colaborador #{$c->id}");

        $out = [];

        foreach ($entries as $entry) {
            $key = "{$entry->seller_id}|{$entry->source_period_start->toDateString()}|{$entry->source_period_end->toDateString()}";

            $out[$key] ??= [
                'amount'       => 0.0,
                'nombre'       => $nombres[$entry->colaborador_id] ?? "colaborador #{$entry->colaborador_id}",
                'seller_id'    => (int) $entry->seller_id,
                'period_start' => $entry->source_period_start->toDateString(),
                'period_end'   => $entry->source_period_end->toDateString(),
            ];
            $out[$key]['amount'] += (float) $entry->amount;
        }

        return $out;
    }

    /**
     * @param array<string,array<string,mixed>> $vendedores
     * @param array<string,array<string,mixed>> $espejo
     * @return array<int,array{seller_id:int,nombre:string,periodo:string,monto_vendedores:float,monto_espejo:float,diferencia:float,estado:string,colaborador_id:?int,period_start:string,period_end:string}>
     */
    private function construirDiff(array $vendedores, array $espejo): array
    {
        $keys = array_unique(array_merge(array_keys($vendedores), array_keys($espejo)));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            [$sellerId, $periodStart, $periodEnd] = explode('|', $key);

            $montoVendedores = $vendedores[$key]['amount'] ?? 0.0;
            $montoEspejo     = $espejo[$key]['amount'] ?? 0.0;
            $nombre          = $vendedores[$key]['nombre'] ?? $espejo[$key]['nombre'] ?? "seller #{$sellerId}";

            // Ventana ORIGINAL sin remapear — debe cuadrar EXACTO (es copia directa, sin
            // resolución de calendario de por medio). Tolerancia cero, sin ruido de float.
            $diffCentavos = (int) round(($montoVendedores - $montoEspejo) * 100);

            if (!isset($vendedores[$key])) {
                $estado = 'FALTA EN VENDEDORES';
            } elseif (!isset($espejo[$key])) {
                $estado = 'FALTA EN ESPEJO';
            } elseif ($diffCentavos !== 0) {
                $estado = 'MONTO DISTINTO';
            } else {
                $estado = 'OK';
            }

            $colaborador = $this->resolveColaborador((int) $sellerId);

            $rows[] = [
                'seller_id'        => (int) $sellerId,
                'colaborador_id'   => $colaborador['colaborador_id'] ?? null,
                'nombre'           => $nombre,
                'periodo'          => "{$periodStart} → {$periodEnd}",
                'period_start'     => $periodStart,
                'period_end'       => $periodEnd,
                'monto_vendedores' => $montoVendedores,
                'monto_espejo'     => $montoEspejo,
                'diferencia'       => $diffCentavos / 100,
                'estado'           => $estado,
            ];
        }

        return $rows;
    }

    private function resolveColaborador(int $sellerId): ?array
    {
        if (array_key_exists($sellerId, $this->colaboradorPorSeller)) {
            return $this->colaboradorPorSeller[$sellerId];
        }

        $userId      = Seller::where('id', $sellerId)->value('user_id');
        $colaborador = $userId ? TalentoColaborador::with('user')->where('user_id', $userId)->first() : null;

        $resuelto = $colaborador
            ? ['colaborador_id' => $colaborador->id, 'nombre' => $colaborador->user?->name ?? "user #{$userId}"]
            : null;

        return $this->colaboradorPorSeller[$sellerId] = $resuelto;
    }

    /**
     * Solo persiste las filas con diferencia real (estado <> OK) — decisión Irving q3. El
     * detalle "sin diferencias" queda en el log de archivo, no aquí.
     *
     * @param array<int,array<string,mixed>> $discrepancias
     */
    private function registrarAuditoria(array $discrepancias): void
    {
        foreach ($discrepancias as $r) {
            TalentoReconciliacionLog::create([
                'colaborador_id'       => $r['colaborador_id'],
                'seller_id'            => $r['seller_id'],
                'source_period_start'  => $r['period_start'],
                'source_period_end'    => $r['period_end'],
                'monto_vendedores'     => $r['monto_vendedores'],
                'monto_espejo'         => $r['monto_espejo'],
                'diferencia'           => $r['diferencia'],
                'estado'               => $r['estado'],
            ]);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $discrepancias
     * @param array<int,float> $sinColaborador
     */
    private function registrarLog(?string $periodo, int $totalFilas, array $discrepancias, array $sinColaborador): void
    {
        $canal = Log::channel('talento_reconciliacion');

        if (empty($discrepancias)) {
            $canal->info('Reconciliación espejo sin discrepancias.', [
                'periodo'      => $periodo ?? 'todos',
                'total_filas'  => $totalFilas,
            ]);
            return;
        }

        $canal->warning('Reconciliación espejo encontró discrepancias.', [
            'periodo'          => $periodo ?? 'todos',
            'total_filas'      => $totalFilas,
            'discrepancias'    => count($discrepancias),
            'sin_colaborador'  => $sinColaborador,
            'detalle'          => array_map(fn ($r) => [
                'seller_id'         => $r['seller_id'],
                'colaborador_id'    => $r['colaborador_id'],
                'periodo'           => $r['periodo'],
                'monto_vendedores'  => $r['monto_vendedores'],
                'monto_espejo'      => $r['monto_espejo'],
                'diferencia'        => $r['diferencia'],
                'estado'            => $r['estado'],
            ], $discrepancias),
        ]);
    }
}
