<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Reporte de reconciliación del backfill invoices↔client_invoices (roadmap #632, item #749,
 * punto (3): q3 de #722 — "reporte que Irving aprueba antes de cerrar").
 *
 * SOLO LECTURA. No escribe en invoices ni en client_invoices. Compara ambas tablas
 * post-backfill: conteos, checksum de totales, folios únicos, desglose de saldos por status
 * y el detalle de las filas needs_review con su motivo.
 */
class InvoicesReporteReconciliacionBackfillCommand extends Command
{
    protected $signature = 'invoices:reporte-reconciliacion-backfill
                            {--guardar : Además de imprimir, guarda el reporte en storage/logs}';

    protected $description = 'Reporte de solo lectura: reconcilia client_invoices vs invoices tras el backfill (#749)';

    public function handle(): int
    {
        $lineas = [];
        $lineas[] = '=== Reporte de reconciliación — backfill invoices↔client_invoices (#749) ===';
        $lineas[] = 'Generado: ' . now()->toDateTimeString();
        $lineas[] = 'Solo lectura — no se escribió nada en invoices ni en client_invoices.';
        $lineas[] = '';

        // -- Conteos --
        $totalClientInvoices = DB::table('client_invoices')->count();
        $huerfanos = DB::table('client_invoices as ci')
            ->leftJoin('clients as c', 'c.id', '=', 'ci.client_id')
            ->whereNull('c.id')
            ->count();
        $matchables = $totalClientInvoices - $huerfanos;
        $invoicesEspejo = DB::table('invoices')->whereNotNull('client_invoice_id')->count();

        $lineas[] = '-- Conteos --';
        $lineas[] = "client_invoices (universo total): {$totalClientInvoices}";
        $lineas[] = "  con match de cliente (elegibles al espejo): {$matchables}";
        $lineas[] = "  huérfanas (client_id sin match en clients, excluidas por FK): {$huerfanos}";
        $lineas[] = "invoices con client_invoice_id poblado (espejo real): {$invoicesEspejo}";
        $diffConteo = $matchables - $invoicesEspejo;
        $lineas[] = "Diferencia elegibles vs espejo real: {$diffConteo}" . ($diffConteo === 0 ? '  ✅' : '  ⚠️ REVISAR');
        $lineas[] = '';

        // -- Checksum de totales --
        $sumClientInvoicesMatch = DB::table('client_invoices as ci')
            ->join('clients as c', 'c.id', '=', 'ci.client_id')
            ->sum('ci.total');
        $sumInvoicesEspejo = DB::table('invoices')->whereNotNull('client_invoice_id')->sum('total');
        $diffChecksum = bcsub((string) $sumClientInvoicesMatch, (string) $sumInvoicesEspejo, 4);

        $lineas[] = '-- Checksum de totales --';
        $lineas[] = "SUM(client_invoices.total) [con match cliente]: {$sumClientInvoicesMatch}";
        $lineas[] = "SUM(invoices.total) [espejo]: {$sumInvoicesEspejo}";
        $lineas[] = "Diferencia: {$diffChecksum}" . ((float) $diffChecksum === 0.0 ? '  ✅ cuadra exacto' : '  ⚠️ REVISAR');
        $lineas[] = '';

        // -- Folios únicos --
        $foliosClientInvoices = DB::table('client_invoices')->distinct('number')->count('number');
        $foliosInvoicesEspejo = DB::table('invoices')->whereNotNull('client_invoice_id')->distinct('number')->count('number');
        $lineas[] = '-- Folios únicos --';
        $lineas[] = "client_invoices.number distintos: {$foliosClientInvoices} (de {$totalClientInvoices} filas — puede haber duplicados en el origen)";
        $lineas[] = "invoices.number distintos [espejo, patrón CI-{id}]: {$foliosInvoicesEspejo} (siempre único 1:1 por diseño, ver UNIQUE client_invoice_id)";
        $lineas[] = '';

        // -- Desglose de saldos por status --
        $lineas[] = '-- Desglose de saldos por status (invoices, espejo) --';
        $porStatus = DB::table('invoices')
            ->whereNotNull('client_invoice_id')
            ->select('status', DB::raw('COUNT(*) as n'), DB::raw('SUM(total) as suma_total'), DB::raw('SUM(pending_balance) as suma_pendiente'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        foreach ($porStatus as $s) {
            $lineas[] = sprintf('  %-16s n=%-8d total=%14s pendiente=%14s', $s->status, $s->n, $s->suma_total, $s->suma_pendiente);
        }
        $lineas[] = '';

        // -- needs_review: detalle --
        $needsReviewTotal = DB::table('invoices')->whereNotNull('client_invoice_id')->where('needs_review', 1)->count();
        $lineas[] = "-- needs_review: {$needsReviewTotal} filas en el espejo --";
        $porMotivo = DB::table('invoices')
            ->whereNotNull('client_invoice_id')
            ->where('needs_review', 1)
            ->select('review_reason', DB::raw('COUNT(*) as n'))
            ->groupBy('review_reason')
            ->orderByDesc('n')
            ->get();
        foreach ($porMotivo as $m) {
            $lineas[] = "  \"{$m->review_reason}\": {$m->n}";
        }
        $lineas[] = '';
        $lineas[] = '  Detalle completo (client_invoice_id → motivo):';
        $detalle = DB::table('invoices')
            ->whereNotNull('client_invoice_id')
            ->where('needs_review', 1)
            ->orderBy('client_invoice_id')
            ->get(['client_invoice_id', 'client_id', 'total', 'status', 'review_reason']);
        foreach ($detalle as $d) {
            $lineas[] = "    client_invoice_id={$d->client_invoice_id} client_id={$d->client_id} total={$d->total} status={$d->status} motivo=\"{$d->review_reason}\"";
        }
        $lineas[] = '';

        // -- Huérfanas excluidas: detalle --
        $lineas[] = "-- Huérfanas excluidas del espejo (client_id sin match — {$huerfanos} filas) --";
        $huerfanasDetalle = DB::table('client_invoices as ci')
            ->leftJoin('clients as c', 'c.id', '=', 'ci.client_id')
            ->whereNull('c.id')
            ->orderBy('ci.id')
            ->limit(100)
            ->get(['ci.id', 'ci.client_id', 'ci.total', 'ci.estado']);
        foreach ($huerfanasDetalle as $h) {
            $lineas[] = "    client_invoices.id={$h->id} client_id={$h->client_id} total={$h->total} estado=\"{$h->estado}\"";
        }
        if ($huerfanos > 100) {
            $lineas[] = '    ... (' . ($huerfanos - 100) . ' más, tope de 100 en el detalle impreso)';
        }
        $lineas[] = '';
        $lineas[] = '=== Fin del reporte — client_invoices y invoices sin modificar ===';

        $reporte = implode(PHP_EOL, $lineas);
        $this->line($reporte);

        if ($this->option('guardar')) {
            $ruta = storage_path('logs/invoices-reporte-reconciliacion-' . now()->format('Y-m-d_His') . '.log');
            File::put($ruta, $reporte);
            $this->info("Reporte guardado en: {$ruta}");
        }

        return self::SUCCESS;
    }
}
