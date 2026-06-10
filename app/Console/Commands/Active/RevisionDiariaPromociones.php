<?php

namespace App\Console\Commands\Active;

use App\Models\ClientPlanPromotion;
use App\Models\OltOnu;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Revisión diaria de promociones — corre a las 6:00 AM.
 *
 * 1. Aplica reversiones pendientes que el hourly haya dejado sin resolver.
 * 2. Reconciliación: compara velocidad real (olt_onus.service_ports) contra
 *    lo que DEBERÍA tener cada promo activa o terminada de los últimos 60 días.
 * 3. Lista promos activas que vencen HOY.
 * 4. Genera resumen estructurado [RevisionDiaria] en laravel.log.
 */
class RevisionDiariaPromociones extends Command
{
    protected $signature = 'promociones:revision-diaria';

    protected $description = 'Revisión diaria 6 AM: reversiones pendientes, reconciliación BD vs ONU, promos que vencen hoy';

    public function handle()
    {
        $startedAt = now();
        $this->logInfo('=== Inicio revisión diaria de promociones ===');

        // ── 1. Reversiones pendientes ─────────────────────────────────────────
        $this->logInfo('Paso 1/3 — Reversiones pendientes (caducadas+activas)');
        Artisan::call('smartolt:sync-promotions');
        $output = trim(Artisan::output());
        foreach (explode("\n", $output) as $line) {
            if ($line !== '') {
                $this->logInfo("sync-promotions | {$line}");
                $this->line("  {$line}");
            }
        }

        // ── 2. Reconciliación BD vs service_ports ────────────────────────────
        $this->logInfo('Paso 2/3 — Reconciliación BD vs ONU (últimos 60 días)');
        $mismatches = $this->reconcile();

        // ── 3. Promos que vencen HOY ─────────────────────────────────────────
        $this->logInfo('Paso 3/3 — Promos activas que vencen hoy');
        $expiringToday = $this->checkExpiringToday();

        // ── Resumen final ─────────────────────────────────────────────────────
        $elapsed = now()->diffInSeconds($startedAt);
        $summary = sprintf(
            'Resumen: mismatches_encontrados=%d promos_vencen_hoy=%d tiempo=%ds',
            count($mismatches),
            count($expiringToday),
            $elapsed
        );

        $level = count($mismatches) > 0 ? 'warning' : 'info';
        $this->logInfo($summary, $level);
        $this->info("[RevisionDiaria] {$summary}");

        if (count($mismatches) > 0) {
            $this->logInfo(
                'ATENCIÓN: se detectaron mismatches. Ver laravel.log [RevisionDiaria][MISMATCH] para detalles.',
                'warning'
            );
            $this->warn('[RevisionDiaria] Hay mismatches — revisar log para corrección manual.');
        }

        $this->logInfo('=== Fin revisión diaria ===');
        return self::SUCCESS;
    }

    /**
     * Compara la velocidad real en olt_onus.service_ports contra:
     *  - Promos active     → debería tener current_*
     *  - Promos closed/canceled (últimos 60 días) → debería tener before_*
     *
     * Los service_ports son actualizados diariamente por smartolt:sync-inventory (5 AM),
     * por lo que a las 6 AM la lectura es fresca sin llamar a SmartOLT.
     */
    private function reconcile(): array
    {
        $cutoff = Carbon::now()->subDays(60);
        $mismatches = [];

        $promos = ClientPlanPromotion::where(function ($q) use ($cutoff) {
            $q->where('status', 'active')
              ->orWhere(function ($q2) use ($cutoff) {
                  $q2->whereIn('status', ['closed', 'canceled'])
                     ->where('updated_at', '>=', $cutoff);
              });
        })->get();

        $this->logInfo(sprintf('Reconciliación: revisando %d promos', $promos->count()));

        foreach ($promos as $p) {
            $onu = OltOnu::firstWhere('client_id', $p->client_id);
            if (!$onu || empty($onu->service_ports)) {
                continue;
            }

            $ports     = $onu->service_ports;
            $actualDl  = $ports[0]['download_speed'] ?? null;
            $actualUl  = $ports[0]['upload_speed']   ?? null;

            if ($p->status === 'active') {
                // Activa: ONU debería estar en velocidad promocional
                $expectedDl = $p->current_download_profile;
                $expectedUl = $p->current_upload_profile;
                $label      = 'active';
            } else {
                // Terminada: ONU debería estar de vuelta en la velocidad original
                $expectedDl = $p->before_download_profile;
                $expectedUl = $p->before_upload_profile;
                $label      = $p->status;
            }

            if ($actualDl !== $expectedDl || $actualUl !== $expectedUl) {
                $msg = sprintf(
                    'client_id=%d promo_id=%d status=%s — ONU tiene dl=%s ul=%s, esperado dl=%s ul=%s',
                    $p->client_id, $p->id, $label,
                    $actualDl, $actualUl, $expectedDl, $expectedUl
                );
                $this->logInfo("[MISMATCH] {$msg}", 'warning');
                $this->warn("[RevisionDiaria][MISMATCH] {$msg}");
                $mismatches[] = [
                    'client_id' => $p->client_id,
                    'promo_id'  => $p->id,
                    'status'    => $label,
                    'actual_dl' => $actualDl,
                    'actual_ul' => $actualUl,
                    'expected_dl' => $expectedDl,
                    'expected_ul' => $expectedUl,
                ];
            }
        }

        if (empty($mismatches)) {
            $this->logInfo('Reconciliación: sin mismatches detectados.');
            $this->info('[RevisionDiaria] Reconciliación OK — sin mismatches.');
        }

        return $mismatches;
    }

    private function checkExpiringToday(): array
    {
        $expiring = ClientPlanPromotion::where('status', 'active')
            ->whereDate('end_at', Carbon::today())
            ->get(['id', 'client_id', 'end_at', 'current_download_profile', 'before_download_profile']);

        if ($expiring->isEmpty()) {
            $this->logInfo('Ninguna promo vence hoy.');
            $this->info('[RevisionDiaria] Ninguna promo vence hoy.');
            return [];
        }

        foreach ($expiring as $p) {
            $msg = sprintf(
                'Vence HOY: promo_id=%d client_id=%d current_dl=%s → before_dl=%s',
                $p->id, $p->client_id, $p->current_download_profile, $p->before_download_profile
            );
            $this->logInfo("[VENCE_HOY] {$msg}", 'warning');
            $this->warn("[RevisionDiaria][VENCE_HOY] {$msg}");
        }

        return $expiring->all();
    }

    private function logInfo(string $message, string $level = 'info'): void
    {
        Log::$level('[RevisionDiaria] ' . $message);
    }
}
