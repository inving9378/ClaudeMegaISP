<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Fase 3a de la unificación invoices/client_invoices (roadmap #632, sub-item #748).
 *
 * SOLO REPORTA. En esta fase el comando nunca escribe en `invoices` — ni siquiera sin
 * --dry-run (eso es la Fase 3b). Recorre `client_invoices` en chunks, calcula en memoria el
 * mapeo que tendría cada fila hacia `invoices` (status/type/fechas/needs_review) y acumula
 * estadísticas + muestras, sin insertar nada. El mapeo replica exactamente el spec del item
 * #748 (conteos por estado ya confirmados contra la BD real de dev).
 */
class BackfillInvoicesFromClientInvoicesCommand extends Command
{
    protected $signature = 'invoices:backfill-from-client-invoices
                            {--dry-run : Sin efecto en esta fase — el comando siempre es de solo reporte (ver Fase 3b)}
                            {--chunk=2000 : Tamaño de chunk para recorrer client_invoices}
                            {--limit= : Tope opcional de filas a procesar (pruebas)}';

    protected $description = 'Fase 3a (#748): reporta el mapeo client_invoices→invoices sin escribir nada (dry-run permanente por ahora)';

    private const ESTADO_A_STATUS = [
        'Pagado' => 'paid',
        'Pagado (del saldo de la cuenta)' => 'paid',
        'Pagar (del saldo de la cuenta)' => 'issued',
        'impagado' => 'issued',
        'Atrasado' => 'overdue',
        'Partially paid' => 'partially_paid',
    ];

    private const SAMPLE_SIZE = 5;

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $invoicesCountAntes = DB::table('invoices')->count();
        $totalClientInvoices = DB::table('client_invoices')->count();
        $totalUniverso = $limit !== null ? min($limit, $totalClientInvoices) : $totalClientInvoices;

        $this->info('Fase 3a — reporte de solo lectura. No se escribirá NADA en invoices.');
        $this->info("client_invoices: {$totalClientInvoices} filas totales; procesando {$totalUniverso}.");

        $stats = [
            'procesadas' => 0,
            'status' => ['paid' => 0, 'issued' => 0, 'overdue' => 0, 'partially_paid' => 0],
            'type' => ['proforma' => 0, 'payment' => 0],
            'needs_review_total' => 0,
            'motivos' => [
                'estado desconocido' => 0,
                'fecha no parseable' => 0,
                'partially_paid sin monto exacto de saldo' => 0,
                'client_id sin match en clients' => 0,
            ],
            'huerfanos' => 0,
            'match_ok' => 0,
            'duplicados_potenciales' => 0,
            'checksum_total' => '0',
        ];

        $samples = array_fill_keys(array_keys($stats['motivos']), []);

        $query = DB::table('client_invoices as ci')
            ->leftJoin('clients as c', 'c.id', '=', 'ci.client_id')
            ->select([
                'ci.id as id',
                'ci.client_id',
                'ci.total',
                'ci.estado',
                'ci.is_proforma',
                'ci.document_date',
                'ci.payment_date',
                DB::raw("STR_TO_DATE(ci.document_date, '%d/%m/%Y') as due_date_parsed"),
                DB::raw("STR_TO_DATE(ci.payment_date, '%d/%m/%Y') as payment_date_parsed"),
                DB::raw('c.id as client_existe'),
            ])
            ->orderBy('ci.id');

        $procesadas = 0;
        $detener = false;

        $query->chunkById($chunk, function ($rows) use (&$stats, &$samples, &$procesadas, $limit, &$detener) {
            if ($detener) {
                return false;
            }

            $ids = [];

            foreach ($rows as $row) {
                if ($limit !== null && $procesadas >= $limit) {
                    $detener = true;
                    break;
                }

                $procesadas++;
                $ids[] = $row->id;

                $motivos = [];

                $status = self::ESTADO_A_STATUS[$row->estado] ?? null;
                if ($status === null) {
                    $status = 'issued';
                    $motivos[] = 'estado desconocido';
                }
                $stats['status'][$status]++;

                $type = ((int) $row->is_proforma === 1) ? 'proforma' : 'payment';
                $stats['type'][$type]++;

                $documentDateVacia = in_array($row->document_date, ['', '0', null], true);
                if (! $documentDateVacia && $row->due_date_parsed === null) {
                    $motivos[] = 'fecha no parseable';
                }

                $paymentDateVacia = in_array($row->payment_date, ['', '0', null], true);
                if (! $paymentDateVacia && $row->payment_date_parsed === null && ! in_array('fecha no parseable', $motivos, true)) {
                    $motivos[] = 'fecha no parseable';
                }

                if ($row->estado === 'Partially paid') {
                    $motivos[] = 'partially_paid sin monto exacto de saldo';
                }

                if ($row->client_existe === null) {
                    $motivos[] = 'client_id sin match en clients';
                    $stats['huerfanos']++;
                } else {
                    $stats['match_ok']++;
                }

                $stats['checksum_total'] = bcadd($stats['checksum_total'], (string) $row->total, 2);

                if (! empty($motivos)) {
                    $stats['needs_review_total']++;
                    foreach (array_unique($motivos) as $motivo) {
                        $stats['motivos'][$motivo]++;
                        if (count($samples[$motivo]) < self::SAMPLE_SIZE) {
                            $samples[$motivo][] = sprintf(
                                'client_invoices.id=%d client_id=%d estado="%s" document_date="%s" payment_date="%s" total=%s',
                                $row->id,
                                $row->client_id,
                                $row->estado,
                                $row->document_date,
                                (string) $row->payment_date,
                                $row->total
                            );
                        }
                    }
                }
            }

            if (! empty($ids)) {
                $stats['duplicados_potenciales'] += DB::table('invoices')
                    ->whereIn('client_invoice_id', $ids)
                    ->count();
            }

            $this->output->write('.');

            return ! $detener;
        }, 'ci.id', 'id');

        $stats['procesadas'] = $procesadas;
        $this->newLine(2);

        $invoicesCountDespues = DB::table('invoices')->count();

        $reporte = $this->construirReporte($stats, $samples, $invoicesCountAntes, $invoicesCountDespues, $totalClientInvoices);

        $this->line($reporte);

        $rutaLog = storage_path('logs/invoices-backfill-dry-run-' . now()->format('Y-m-d_His') . '.log');
        File::put($rutaLog, $reporte);
        $this->info("Reporte completo escrito en: {$rutaLog}");

        if ($invoicesCountAntes !== $invoicesCountDespues) {
            $this->error('⚠️ ALERTA: invoices cambió de conteo durante la corrida — esto NO debería pasar en Fase 3a.');

            return self::FAILURE;
        }

        $this->info("✅ Verificado: invoices sin cambios ({$invoicesCountAntes} filas antes y después).");

        return self::SUCCESS;
    }

    private function construirReporte(array $stats, array $samples, int $antes, int $despues, int $totalClientInvoices): string
    {
        $lineas = [];
        $lineas[] = '=== Reporte Fase 3a — invoices:backfill-from-client-invoices (solo lectura) ===';
        $lineas[] = 'Generado: ' . now()->toDateTimeString();
        $lineas[] = '';
        $lineas[] = '-- Totales por tabla --';
        $lineas[] = "client_invoices (universo): {$totalClientInvoices}";
        $lineas[] = "client_invoices procesadas esta corrida: {$stats['procesadas']}";
        $lineas[] = "invoices (antes): {$antes}";
        $lineas[] = "invoices (después): {$despues}";
        $lineas[] = '';
        $lineas[] = '-- Match / huérfanos --';
        $lineas[] = "Harían match (client_id existe en clients): {$stats['match_ok']}";
        $lineas[] = "Huérfanos (client_id SIN match en clients): {$stats['huerfanos']}";
        $lineas[] = "Duplicados potenciales (client_invoice_id ya poblado en invoices): {$stats['duplicados_potenciales']}";
        $lineas[] = '';
        $lineas[] = '-- Breakdown por status resultante --';
        foreach ($stats['status'] as $status => $n) {
            $lineas[] = "  {$status}: {$n}";
        }
        $lineas[] = '';
        $lineas[] = '-- Breakdown por type --';
        foreach ($stats['type'] as $type => $n) {
            $lineas[] = "  {$type}: {$n}";
        }
        $lineas[] = '';
        $lineas[] = "-- needs_review: {$stats['needs_review_total']} filas --";
        foreach ($stats['motivos'] as $motivo => $n) {
            $lineas[] = "  {$motivo}: {$n}";
        }
        $lineas[] = '';
        $lineas[] = '-- Checksum --';
        $lineas[] = "SUM(client_invoices.total) del universo procesado: {$stats['checksum_total']}";
        $lineas[] = '';
        $lineas[] = '-- Muestras (hasta ' . self::SAMPLE_SIZE . ' por motivo) --';
        foreach ($samples as $motivo => $filas) {
            $lineas[] = "  [{$motivo}]";
            if (empty($filas)) {
                $lineas[] = '    (sin filas)';

                continue;
            }
            foreach ($filas as $fila) {
                $lineas[] = "    {$fila}";
            }
        }
        $lineas[] = '';
        $lineas[] = '=== Fin del reporte — no se escribió nada en invoices ===';

        return implode(PHP_EOL, $lineas);
    }
}
