<?php

namespace App\Modules\Addons\Talento\Console;

use App\Models\Seller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoComisionEspejo;
use App\Modules\Addons\Talento\Support\PayWeek;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 (item #9990606) del plan de migración de comisiones de vendedor a Talento
 * (ver docs/talento-comisiones-migracion-analisis-item-9990453.md §4.3).
 *
 * 100% SOLO LECTURA — no escribe en payment_by_rule ni en talento_comisiones_espejo, no
 * corrige nada. Compara, por colaborador y período de pago (PayWeek), la suma del motor
 * viejo (payment_by_rule_commissions vía payment_by_rule) contra la suma del espejo nuevo
 * (tabla aislada talento_comisiones_espejo, escrita por la Fase 1b #9990610 -- NO
 * talento_ledger_entries, que LiquidationService::calculate() sí suma al grossPay real) y
 * reporta CUALQUIER discrepancia (monto distinto / falta en un lado / posible duplicado)
 * con tolerancia CERO (decisión de Irving, q2 del item): no hay margen de redondeo, todo
 * lo que no cuadre al centavo se lista para que Irving lo resuelva caso por caso.
 *
 * Este reporte es el gate de la Fase 3 (conmutación): esa fase no se ejecuta hasta que,
 * corriendo esto durante el período de prueba que decida Irving, deje de haber filas
 * fuera de "OK".
 */
class ConciliarComisionesVendedorCommand extends Command
{
    protected $signature = 'talento:conciliar-comisiones-vendedor
        {--desde= : Fecha inicial Y-m-d. Default: apertura de la semana de pago vigente (PayWeek).}
        {--hasta= : Fecha final Y-m-d. Default: hoy (o cierre de la semana vigente si --desde tampoco se dio).}
        {--csv= : Ruta del CSV de salida. Default: storage/app/reportes/conciliacion-comisiones-vendedor-*.csv.}';

    protected $description = 'SOLO LECTURA — Fase 2 #9990606: concilia al centavo payment_by_rule (motor viejo) '
        . 'contra talento_comisiones_espejo (espejo Fase 1a/1b #9990609/#9990610), por colaborador/período.';

    /** @var array<int, array{colaborador_id:int,nombre:string}|null> cache seller_id → colaborador resuelto */
    private array $colaboradorPorSeller = [];

    public function handle(): int
    {
        [$desde, $hasta] = $this->resolveRange();

        $this->info("Conciliando comisiones de vendedor del {$desde->toDateString()} al {$hasta->toDateString()}…");
        $this->line('(Solo lectura — no se escribe nada en payment_by_rule ni en talento_comisiones_espejo.)');
        $this->newLine();

        [$viejo, $sinColaborador] = $this->sumarMotorViejo($desde, $hasta);
        [$nuevo, $duplicados]     = $this->sumarEspejoLedger($desde, $hasta);

        $rows = $this->construirDiff($viejo, $nuevo);

        $discrepancias = count(array_filter($rows, fn ($r) => $r['estado'] !== 'OK'));

        $this->table(
            ['Colaborador', 'Nombre', 'Período (PayWeek)', 'Motor viejo', 'Espejo Talento', 'Diferencia', 'Estado'],
            array_map(fn ($r) => [
                $r['colaborador_id'],
                $r['nombre'],
                $r['periodo'],
                number_format($r['monto_viejo'], 2),
                number_format($r['monto_nuevo'], 2),
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

        if ($duplicados) {
            $this->newLine();
            $this->warn('Posibles duplicados en el espejo (mismo payment_by_rule_details_id con más de una fila):');
            foreach ($duplicados as $refKey => $ids) {
                $this->line("  - {$refKey} → talento_comisiones_espejo.id: " . implode(', ', $ids));
            }
        }

        $csvPath = $this->writeCsv($rows, $sinColaborador, $duplicados, $desde, $hasta);

        $this->newLine();
        $this->info("CSV escrito en: {$csvPath}");
        $this->info(
            $discrepancias === 0 && !$duplicados
                ? 'Sin discrepancias en el rango conciliado.'
                : "Discrepancias encontradas: {$discrepancias} (+ " . count($duplicados) . ' posibles duplicados).'
        );

        return ($discrepancias === 0 && !$duplicados) ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0:\Carbon\Carbon,1:\Carbon\Carbon} */
    private function resolveRange(): array
    {
        $desdeOpt = $this->option('desde');
        $hastaOpt = $this->option('hasta');

        if (!$desdeOpt && !$hastaOpt) {
            // Default (decisión de Irving, q3): solo el período de pago vigente.
            $current = PayWeek::current();
            return [Carbon::parse($current['period_start'])->startOfDay(), Carbon::parse($current['period_end'])->endOfDay()];
        }

        $hasta = $hastaOpt ? Carbon::parse($hastaOpt)->endOfDay() : Carbon::now()->endOfDay();
        $desde = $desdeOpt ? Carbon::parse($desdeOpt)->startOfDay() : Carbon::parse(PayWeek::current()['period_start'])->startOfDay();

        return [$desde, $hasta];
    }

    /**
     * Motor viejo: payment_by_rule_commissions (detalle) + payment_by_rule (header, trae
     * seller_id y payment_date). Se agrupa por colaborador + ventana PayWeek resuelta a
     * partir de payment_date (el momento del evento de pago, que es cuando la Fase 1
     * escribiría el espejo).
     *
     * @return array{0:array<string,array{amount:float,nombre:string}>,1:array<int,float>}
     */
    private function sumarMotorViejo(Carbon $desde, Carbon $hasta): array
    {
        $rows = DB::table('payment_by_rule_commissions as d')
            ->join('payment_by_rule as p', 'p.id', '=', 'd.payment_id')
            ->whereBetween('p.payment_date', [$desde->toDateString(), $hasta->toDateString()])
            ->select('d.amount', 'p.seller_id', 'p.payment_date')
            ->get();

        $out            = [];
        $sinColaborador = [];

        foreach ($rows as $row) {
            $colaborador = $this->resolveColaborador((int) $row->seller_id);

            if ($colaborador === null) {
                $sinColaborador[$row->seller_id] = ($sinColaborador[$row->seller_id] ?? 0.0) + (float) $row->amount;
                continue;
            }

            $window = PayWeek::boundsFor(Carbon::parse($row->payment_date));
            $key    = "{$colaborador['colaborador_id']}|{$window['period_start']}|{$window['period_end']}";

            $out[$key] ??= ['amount' => 0.0, 'nombre' => $colaborador['nombre']];
            $out[$key]['amount'] += (float) $row->amount;
        }

        return [$out, $sinColaborador];
    }

    /**
     * Espejo nuevo: talento_comisiones_espejo (tabla AISLADA escrita por la Fase 1b
     * #9990610 -- a propósito NO talento_ledger_entries, para que LiquidationService::
     * calculate() no la sume al grossPay real), agrupado por colaborador + su propio
     * period_start/period_end. Esas columnas YA vienen resueltas a PayWeek por la Fase
     * 1b, no hace falta recalcularlas aquí (a diferencia de sumarMotorViejo(), que sí
     * tiene que mapear payment_date → PayWeek::boundsFor()).
     *
     * A diferencia del ledger real, el espejo no tiene tipo débito/crédito (cada fila es
     * copia directa y positiva de un renglón del motor viejo) ni reference_id/
     * reference_type -- el candidato natural para detectar duplicados es
     * payment_by_rule_details_id (debería aparecer una sola vez por fila espejada).
     *
     * @return array{0:array<string,array{amount:float,nombre:string}>,1:array<string,array<int,int>>}
     */
    private function sumarEspejoLedger(Carbon $desde, Carbon $hasta): array
    {
        $entries = TalentoComisionEspejo::where('period_start', '<=', $hasta->toDateString())
            ->where('period_end', '>=', $desde->toDateString())
            ->get();

        $colaboradorIds = $entries->pluck('colaborador_id')->unique();
        $nombres = TalentoColaborador::with('user')
            ->whereIn('id', $colaboradorIds)
            ->get()
            ->keyBy('id')
            ->map(fn (TalentoColaborador $c) => $c->user?->name ?? "colaborador #{$c->id}");

        $out       = [];
        $refVistos = [];

        foreach ($entries as $entry) {
            $nombre = $nombres[$entry->colaborador_id] ?? "colaborador #{$entry->colaborador_id} (no encontrado)";
            $key    = "{$entry->colaborador_id}|{$entry->period_start->toDateString()}|{$entry->period_end->toDateString()}";

            $out[$key] ??= ['amount' => 0.0, 'nombre' => $nombre];
            $out[$key]['amount'] += (float) $entry->amount;

            if ($entry->payment_by_rule_details_id !== null) {
                $refKey               = 'payment_by_rule_details#' . $entry->payment_by_rule_details_id;
                $refVistos[$refKey][] = $entry->id;
            }
        }

        $duplicados = array_filter($refVistos, fn (array $ids) => count($ids) > 1);

        return [$out, $duplicados];
    }

    /**
     * @param array<string,array{amount:float,nombre:string}> $viejo
     * @param array<string,array{amount:float,nombre:string}> $nuevo
     * @return array<int,array{colaborador_id:string,nombre:string,periodo:string,monto_viejo:float,monto_nuevo:float,diferencia:float,estado:string}>
     */
    private function construirDiff(array $viejo, array $nuevo): array
    {
        $keys = array_unique(array_merge(array_keys($viejo), array_keys($nuevo)));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            [$colaboradorId, $periodStart, $periodEnd] = explode('|', $key);

            $montoViejo = $viejo[$key]['amount'] ?? 0.0;
            $montoNuevo = $nuevo[$key]['amount'] ?? 0.0;
            $nombre     = $viejo[$key]['nombre'] ?? $nuevo[$key]['nombre'] ?? "colaborador #{$colaboradorId}";

            // Comparación en centavos enteros — tolerancia CERO (q2), sin ruido de float.
            $diffCentavos = (int) round(($montoViejo - $montoNuevo) * 100);

            if (!isset($viejo[$key])) {
                $estado = 'FALTA EN MOTOR VIEJO';
            } elseif (!isset($nuevo[$key])) {
                $estado = 'FALTA EN ESPEJO (Fase 1)';
            } elseif ($diffCentavos !== 0) {
                $estado = 'MONTO DISTINTO';
            } else {
                $estado = 'OK';
            }

            $rows[] = [
                'colaborador_id' => $colaboradorId,
                'nombre'         => $nombre,
                'periodo'        => "{$periodStart} → {$periodEnd}",
                'monto_viejo'    => $montoViejo,
                'monto_nuevo'    => $montoNuevo,
                'diferencia'     => $diffCentavos / 100,
                'estado'         => $estado,
            ];
        }

        return $rows;
    }

    private function resolveColaborador(int $sellerId): ?array
    {
        if (array_key_exists($sellerId, $this->colaboradorPorSeller)) {
            return $this->colaboradorPorSeller[$sellerId];
        }

        $userId = Seller::where('id', $sellerId)->value('user_id');
        $colaborador = $userId ? TalentoColaborador::with('user')->where('user_id', $userId)->first() : null;

        $resuelto = $colaborador
            ? ['colaborador_id' => $colaborador->id, 'nombre' => $colaborador->user?->name ?? "user #{$userId}"]
            : null;

        return $this->colaboradorPorSeller[$sellerId] = $resuelto;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,float> $sinColaborador
     * @param array<string,array<int,int>> $duplicados
     */
    private function writeCsv(array $rows, array $sinColaborador, array $duplicados, Carbon $desde, Carbon $hasta): string
    {
        $csvOpt = $this->option('csv');

        if ($csvOpt) {
            $path = $csvOpt;
            $dir  = dirname($path);
        } else {
            $dir  = storage_path('app/reportes');
            $path = $dir . '/conciliacion-comisiones-vendedor-' . now()->format('Ymd_His') . '.csv';
        }

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fh = fopen($path, 'w');
        fputcsv($fh, ['rango_desde', $desde->toDateString(), 'rango_hasta', $hasta->toDateString()]);
        fputcsv($fh, ['colaborador_id', 'nombre', 'periodo', 'monto_motor_viejo', 'monto_espejo_talento', 'diferencia', 'estado']);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['colaborador_id'], $r['nombre'], $r['periodo'],
                number_format($r['monto_viejo'], 2, '.', ''),
                number_format($r['monto_nuevo'], 2, '.', ''),
                number_format($r['diferencia'], 2, '.', ''),
                $r['estado'],
            ]);
        }

        if ($sinColaborador) {
            fputcsv($fh, []);
            fputcsv($fh, ['sin_colaborador_talento_seller_id', 'monto_sin_conciliar']);
            foreach ($sinColaborador as $sellerId => $monto) {
                fputcsv($fh, [$sellerId, number_format($monto, 2, '.', '')]);
            }
        }

        if ($duplicados) {
            fputcsv($fh, []);
            fputcsv($fh, ['posible_duplicado_referencia', 'talento_comisiones_espejo_ids']);
            foreach ($duplicados as $refKey => $ids) {
                fputcsv($fh, [$refKey, implode(',', $ids)]);
            }
        }

        fclose($fh);

        return $path;
    }
}
