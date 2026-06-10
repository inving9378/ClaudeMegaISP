<?php

namespace App\Console\Commands\Olts;

use App\Models\ClientPlanPromotion;
use App\Models\OltOnu;
use App\Services\OLTsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPromotions extends Command
{
    protected $signature = 'smartolt:sync-promotions {--dry-run : Lista qué haría sin tocar nada}';

    protected $description = 'Revierte los perfiles de velocidad originales en las promociones caducadas';

    private bool $dryRun = false;

    private int $countProcessed  = 0;
    private int $countReverted   = 0;
    private int $countFailed     = 0;
    private int $countMismatches = 0;

    public function handle()
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->info('[SyncPromotions][DRY-RUN] Modo simulación — no se aplicará ningún cambio.');
        }

        $caduced = ClientPlanPromotion::caduced()->active()->get();

        if ($caduced->isEmpty()) {
            $this->log('info', 'Sin promociones caducadas. Nada que hacer.');
            $this->info('[SyncPromotions] Sin promociones caducadas. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->log('info', sprintf('Encontradas %d promociones caducadas. Iniciando reversión.', $caduced->count()));

        $service = new OLTsService();

        foreach ($caduced as $c) {
            $this->countProcessed++;
            $this->processPromotion($c, $service);
            if (!$this->dryRun) {
                sleep(5);
            }
        }

        $this->logSummary();

        return self::SUCCESS;
    }

    private function processPromotion(ClientPlanPromotion $c, OLTsService $service): void
    {
        $context = sprintf('promo_id=%d client_id=%d before_dl=%s before_ul=%s end_at=%s',
            $c->id, $c->client_id,
            $c->before_download_profile, $c->before_upload_profile,
            $c->end_at
        );

        if ($this->dryRun) {
            $this->info("[SyncPromotions][DRY-RUN] Revertería: {$context}");
            $this->countReverted++;
            return;
        }

        try {
            $onu = OltOnu::firstWhere('client_id', $c->client_id);

            if (!$onu) {
                $this->fail($c->id, 'ONU no encontrada para el cliente', $context);
                return;
            }

            if (empty($onu->unique_external_id)) {
                $this->fail($c->id, 'ONU sin unique_external_id configurado', $context);
                return;
            }

            $portData = $onu->service_ports;
            if (empty($portData)) {
                $this->fail($c->id, 'ONU sin service_ports configurados', $context);
                return;
            }

            $this->log('info', "Revirtiendo: {$context}");

            $response = $service->updateSpeedProfile($onu->unique_external_id, [
                'upload_speed_profile_name'   => $c->before_upload_profile,
                'download_speed_profile_name' => $c->before_download_profile,
            ]);

            if (!$response['success']) {
                $apiMsg = $response['message'] ?? $response['error'] ?? 'SmartOLT devolvió error';
                $this->fail($c->id, "SmartOLT error: {$apiMsg}", $context);
                return;
            }

            // Sincronizar y verificar que el cambio realmente se aplicó
            $syncResult = $service->syncOnu($onu);

            if (!($syncResult['success'] ?? false)) {
                $this->fail($c->id, 'syncOnu falló tras updateSpeedProfile exitoso', $context);
                return;
            }

            $freshOnu   = $syncResult['onu'];
            $freshPorts = $freshOnu->service_ports ?? [];
            $actualDl   = $freshPorts[0]['download_speed'] ?? null;
            $actualUl   = $freshPorts[0]['upload_speed']   ?? null;

            if ($actualDl !== $c->before_download_profile || $actualUl !== $c->before_upload_profile) {
                $msg = sprintf(
                    'MISMATCH tras updateSpeedProfile: ONU muestra dl=%s ul=%s — esperado dl=%s ul=%s. Promo queda active para reintento.',
                    $actualDl, $actualUl, $c->before_download_profile, $c->before_upload_profile
                );
                $this->log('warning', "[MISMATCH] {$context} — {$msg}");
                $this->error("[SyncPromotions][MISMATCH] promo_id={$c->id}: {$msg}");
                $this->countMismatches++;
                return;
            }

            $c->status = 'closed';
            $c->save();

            $this->countReverted++;
            $this->log('info', "OK cerrada: promo_id={$c->id} client_id={$c->client_id} dl={$actualDl} ul={$actualUl}");
            $this->info("[SyncPromotions] Promo {$c->id} cerrada correctamente.");

        } catch (\Throwable $th) {
            $this->fail($c->id, 'Excepción: ' . $th->getMessage(), $context);
        }
    }

    private function fail(int $promoId, string $reason, string $context): void
    {
        $this->countFailed++;
        $msg = "FALLO promo_id={$promoId}: {$reason} | {$context}";
        $this->log('error', $msg);
        $this->error("[SyncPromotions] {$msg}");
    }

    private function logSummary(): void
    {
        $prefix = $this->dryRun ? '[DRY-RUN] ' : '';
        $summary = sprintf(
            '%sResumen: procesadas=%d revertidas=%d fallidas=%d mismatches=%d',
            $prefix,
            $this->countProcessed,
            $this->countReverted,
            $this->countFailed,
            $this->countMismatches
        );

        $level = ($this->countFailed > 0 || $this->countMismatches > 0) ? 'warning' : 'info';
        $this->log($level, $summary);
        $this->info("[SyncPromotions] {$summary}");

        if (!$this->dryRun && ($this->countFailed > 0 || $this->countMismatches > 0)) {
            $this->log('warning', sprintf(
                'ATENCIÓN: %d fallo(s) y %d mismatch(es) requieren revisión manual. Ver laravel.log con prefijo [SyncPromotions].',
                $this->countFailed,
                $this->countMismatches
            ));
        }
    }

    private function log(string $level, string $message): void
    {
        Log::$level('[SyncPromotions] ' . $message);
    }
}
