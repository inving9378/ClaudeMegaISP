<?php

namespace App\Modules\Addons\Flotas\Console;

use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Services\Notifications\DocumentAlertDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDocumentExpirationsCommand extends Command
{
    protected $signature = 'flotas:check-document-expirations
                            {--dry-run : Reporta qué enviaría sin enviar nada}';

    protected $description = 'Revisa vencimientos de documentos de flota y envía alertas por los canales configurados';

    /** Mapa umbral → columna booleana del documento. */
    private const THRESHOLDS = [
        30 => 'alert_30_days',
        7  => 'alert_7_days',
        1  => 'alert_1_day',
        0  => 'alert_same_day',
    ];

    public function handle(DocumentAlertDispatcher $dispatcher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today  = Carbon::today();

        if ($dryRun) {
            $this->info('[DRY-RUN] No se enviará nada — solo diagnóstico.');
        }

        // Solo documentos con vencimiento presente y aún no vencidos (>= hoy).
        $documents = FleetDocument::with('vehicle')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', $today)
            ->whereNull('deleted_at')
            ->get();

        if ($documents->isEmpty()) {
            $this->line('Sin documentos con fecha de vencimiento próxima.');
            return self::SUCCESS;
        }

        $totalAlerts = 0;
        $totalSkipped = 0;

        foreach ($documents as $doc) {
            $daysUntil = (int) $today->diffInDays($doc->expiration_date, false);
            $channels  = $doc->alert_channels ?? [];

            if (empty($channels)) {
                continue;
            }

            $vehicleName = $doc->vehicle?->display_name ?? "Vehículo #{$doc->vehicle_id}";

            foreach (self::THRESHOLDS as $threshold => $column) {
                // El documento debe tener habilitado este umbral.
                if (!$doc->$column) {
                    continue;
                }

                // El umbral dispara si los días restantes son <= umbral.
                if ($daysUntil > $threshold) {
                    continue;
                }

                // Dedup: ¿ya se procesó este umbral para algún canal de este documento?
                // Basta con que exista al menos un registro (cualquier canal) para
                // considerar que el umbral ya fue despachado en una ejecución previa.
                $alreadySent = DB::table('fleet_document_alert_log')
                    ->where('document_id', $doc->id)
                    ->where('threshold', $threshold)
                    ->exists();

                if ($alreadySent) {
                    $totalSkipped++;
                    if ($dryRun) {
                        $this->line("  [SKIP] Doc #{$doc->id} ({$vehicleName} · {$doc->document_type}) umbral {$threshold}d → ya enviado.");
                    }
                    continue;
                }

                if ($dryRun) {
                    $this->warn("  [ENVIARÍA] Doc #{$doc->id} · {$vehicleName} · {$doc->document_type} · {$daysUntil} días · umbral {$threshold}d · canales: " . implode(', ', $channels));
                    $totalAlerts++;
                    continue;
                }

                $results = $dispatcher->dispatch($doc, $threshold, $daysUntil);

                $sent   = count(array_filter($results, fn($r) => $r['status'] === 'sent'));
                $failed = count(array_filter($results, fn($r) => $r['status'] === 'failed'));
                $skip   = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));

                $this->line(
                    "Doc #{$doc->id} · {$vehicleName} · {$doc->document_type} · {$daysUntil}d · "
                    . "umbral {$threshold}d → sent:{$sent} failed:{$failed} skipped:{$skip}"
                );

                $totalAlerts++;
            }
        }

        if ($dryRun) {
            $this->info("[DRY-RUN] {$documents->count()} documento(s) revisados · {$totalAlerts} alerta(s) se enviarían · {$totalSkipped} ya enviadas.");
        } else {
            $this->info("{$documents->count()} documento(s) revisados · {$totalAlerts} alerta(s) procesadas.");
        }

        return self::SUCCESS;
    }
}
