<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Fase 3a/3b de la unificación invoices/client_invoices (roadmap #632, sub-items #748/#749).
 *
 * Con --dry-run: SOLO reporta (comportamiento original de la Fase 3a, sin efecto en `invoices`).
 * Sin --dry-run: además de reportar, escribe de verdad en `invoices` — chunked + insertOrIgnore
 * sobre el UNIQUE de client_invoice_id, así que es idempotente: si el espejo de una fila ya
 * existe, se salta (nunca se pisa ni se actualiza). `client_invoices` NUNCA se toca (ni un UPDATE).
 *
 * Nota (#749): las 110,801 filas ya fueron escritas en una corrida previa sin commitear
 * (2026-08-28 23:16, ver decisión en el item). Este archivo formaliza en código EXACTAMENTE ese
 * mapeo ya aplicado y verificado (checksum/conteos/needs_review coinciden al centavo), para que
 * quede versionado y cualquier re-corrida futura sea un no-op seguro sobre lo ya escrito.
 */
class BackfillInvoicesFromClientInvoicesCommand extends Command
{
    protected $signature = 'invoices:backfill-from-client-invoices
                            {--dry-run : Solo reporta, no escribe nada en invoices}
                            {--chunk=2000 : Tamaño de chunk para recorrer client_invoices}
                            {--limit= : Tope opcional de filas a procesar (pruebas)}';

    protected $description = 'Fase 3a/3b (#748/#749): mapea client_invoices→invoices; con --dry-run solo reporta, sin ella escribe (idempotente vía UNIQUE client_invoice_id)';

    private const ESTADO_A_STATUS = [
        'Pagado' => 'paid',
        'Pagado (del saldo de la cuenta)' => 'paid',
        'Pagar (del saldo de la cuenta)' => 'issued',
        'impagado' => 'issued',
        'Atrasado' => 'overdue',
        'Partially paid' => 'partially_paid',
    ];

    /** Extracción de IVA 16% para separar subtotal/tax (client_invoices solo trae total). */
    private const IVA_RATE = 1.16;

    private const SAMPLE_SIZE = 5;

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $invoicesCountAntes = DB::table('invoices')->count();
        $totalClientInvoices = DB::table('client_invoices')->count();
        $totalUniverso = $limit !== null ? min($limit, $totalClientInvoices) : $totalClientInvoices;

        $this->info(($dry ? '[DRY-RUN] ' : '') . 'client_invoices → invoices. client_invoices NUNCA se modifica.');
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
            'insertadas' => 0,
            'saltadas_ya_existian' => 0,
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
                'ci.is_sent',
                'ci.document_date',
                'ci.payment_date',
                DB::raw("COALESCE(STR_TO_DATE(ci.document_date, '%d/%m/%Y'), STR_TO_DATE(ci.document_date, '%Y-%m-%d')) as due_date_parsed"),
                DB::raw("COALESCE(STR_TO_DATE(ci.payment_date, '%d/%m/%Y'), STR_TO_DATE(ci.payment_date, '%Y-%m-%d')) as payment_date_parsed"),
                DB::raw('c.id as client_existe'),
            ])
            ->orderBy('ci.id');

        $procesadas = 0;
        $detener = false;

        $query->chunkById($chunk, function ($rows) use (&$stats, &$samples, &$procesadas, $limit, &$detener, $dry) {
            if ($detener) {
                return false;
            }

            $ids = [];
            $rowsParaInsertar = [];

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

                // Huérfanas (client_id sin match): invoices.client_id es NOT NULL + FK → no se
                // pueden insertar. Quedan fuera del espejo (documentadas arriba como needs_review
                // conceptual, pero no existe fila en invoices para marcarlas).
                if (! $dry && $row->client_existe !== null) {
                    $total = (float) $row->total;
                    $subtotal = round($total / self::IVA_RATE, 2);
                    $tax = round($total - $subtotal, 2);
                    $pendingBalance = $status === 'paid' ? 0.0 : $total;
                    $dueDate = $row->due_date_parsed;
                    $paymentDate = $row->payment_date_parsed;
                    $period = $dueDate ?? $paymentDate;

                    $rowsParaInsertar[] = [
                        'number' => 'CI-' . $row->id,
                        'client_id' => $row->client_id,
                        'client_invoice_id' => $row->id,
                        'due_date' => $dueDate,
                        'payment_date' => $paymentDate,
                        'is_sent' => (int) $row->is_sent,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'total' => $total,
                        'pending_balance' => $pendingBalance,
                        'status' => $status,
                        'payment_method' => null,
                        'notes' => "Backfill Fase 3b (#749) desde client_invoices.id={$row->id}",
                        'type' => $type,
                        'needs_review' => ! empty($motivos) ? 1 : 0,
                        'review_reason' => ! empty($motivos) ? implode('; ', array_unique($motivos)) : null,
                        'period' => $period !== null ? date('Y-m', strtotime($period)) : now()->format('Y-m'),
                        'created_by' => '0',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($ids)) {
                $stats['duplicados_potenciales'] += DB::table('invoices')
                    ->whereIn('client_invoice_id', $ids)
                    ->count();
            }

            if (! $dry && ! empty($rowsParaInsertar)) {
                $antes = DB::table('invoices')->whereIn('client_invoice_id', $ids)->count();
                DB::table('invoices')->insertOrIgnore($rowsParaInsertar);
                $despues = DB::table('invoices')->whereIn('client_invoice_id', $ids)->count();
                $nuevas = max(0, $despues - $antes);
                $stats['insertadas'] += $nuevas;
                $stats['saltadas_ya_existian'] += count($rowsParaInsertar) - $nuevas;
            }

            $this->output->write('.');

            return ! $detener;
        }, 'ci.id', 'id');

        $stats['procesadas'] = $procesadas;
        $this->newLine(2);

        $invoicesCountDespues = DB::table('invoices')->count();

        $reporte = $this->construirReporte($stats, $samples, $invoicesCountAntes, $invoicesCountDespues, $totalClientInvoices, $dry);

        $this->line($reporte);

        $rutaLog = storage_path('logs/invoices-backfill-' . ($dry ? 'dry-run-' : 'write-') . now()->format('Y-m-d_His') . '.log');
        File::put($rutaLog, $reporte);
        $this->info("Reporte completo escrito en: {$rutaLog}");

        if ($dry && $invoicesCountAntes !== $invoicesCountDespues) {
            $this->error('⚠️ ALERTA: invoices cambió de conteo durante una corrida --dry-run — esto NO debería pasar.');

            return self::FAILURE;
        }

        $this->info($dry
            ? "✅ Verificado: invoices sin cambios ({$invoicesCountAntes} filas antes y después)."
            : "✅ Escritura terminada: {$stats['insertadas']} filas nuevas, {$stats['saltadas_ya_existian']} ya existían (idempotente)."
        );

        return self::SUCCESS;
    }

    private function construirReporte(array $stats, array $samples, int $antes, int $despues, int $totalClientInvoices, bool $dry): string
    {
        $lineas = [];
        $lineas[] = '=== Reporte ' . ($dry ? 'Fase 3a (dry-run)' : 'Fase 3b (escritura real)') . ' — invoices:backfill-from-client-invoices ===';
        $lineas[] = 'Generado: ' . now()->toDateTimeString();
        $lineas[] = '';
        $lineas[] = '-- Totales por tabla --';
        $lineas[] = "client_invoices (universo): {$totalClientInvoices}";
        $lineas[] = "client_invoices procesadas esta corrida: {$stats['procesadas']}";
        $lineas[] = "invoices (antes): {$antes}";
        $lineas[] = "invoices (después): {$despues}";
        if (! $dry) {
            $lineas[] = "Filas nuevas insertadas: {$stats['insertadas']}";
            $lineas[] = "Filas saltadas (ya existían, idempotente): {$stats['saltadas_ya_existian']}";
        }
        $lineas[] = '';
        $lineas[] = '-- Match / huérfanos --';
        $lineas[] = "Harían match (client_id existe en clients): {$stats['match_ok']}";
        $lineas[] = "Huérfanos (client_id SIN match en clients — NO se insertan, FK lo impide): {$stats['huerfanos']}";
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
        $lineas[] = '=== Fin del reporte ' . ($dry ? '— no se escribió nada en invoices' : '') . ' ===';

        return implode(PHP_EOL, $lineas);
    }
}
