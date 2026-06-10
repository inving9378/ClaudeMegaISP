<?php

namespace App\Console\Commands\Active;

use App\Models\OltOnu;
use App\Services\OLTsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando one-off reutilizable para corregir manualmente el perfil de
 * velocidad de una ONU cuando la reversión automática falló silenciosamente
 * (casos tipo cliente 2905: BD dice canceled/closed pero OLT sigue en promo).
 *
 * Uso en producción:
 *   php artisan promociones:corregir-onu 2905 50M 50M
 *
 * Uso en seco (sin tocar SmartOLT):
 *   php artisan promociones:corregir-onu 2905 50M 50M --dry-run
 */
class CorregirOnuPromocion extends Command
{
    protected $signature = 'promociones:corregir-onu
                            {client_id : ID del cliente}
                            {download  : Nombre del perfil de descarga a restaurar (ej: 50M)}
                            {upload    : Nombre del perfil de subida a restaurar (ej: 50M)}
                            {--dry-run : Muestra qué haría sin llamar a SmartOLT}';

    protected $description = 'Restaura el perfil de velocidad de la ONU de un cliente vía SmartOLT y verifica el resultado';

    public function handle()
    {
        $clientId = (int) $this->argument('client_id');
        $download = $this->argument('download');
        $upload   = $this->argument('upload');
        $dryRun   = (bool) $this->option('dry-run');

        $this->info(sprintf(
            '[CorregirOnu] client_id=%d  target_dl=%s  target_ul=%s%s',
            $clientId, $download, $upload,
            $dryRun ? '  [DRY-RUN]' : ''
        ));

        $onu = OltOnu::firstWhere('client_id', $clientId);

        if (!$onu) {
            $this->error("[CorregirOnu] ONU no encontrada para client_id={$clientId}");
            return self::FAILURE;
        }

        if (empty($onu->unique_external_id)) {
            $this->error("[CorregirOnu] ONU sin unique_external_id — no se puede llamar a SmartOLT");
            return self::FAILURE;
        }

        $ports    = $onu->service_ports ?? [];
        $beforeDl = $ports[0]['download_speed'] ?? 'N/A';
        $beforeUl = $ports[0]['upload_speed']   ?? 'N/A';

        $this->info(sprintf(
            '[CorregirOnu] ANTES  — unique_external_id=%s  dl=%s  ul=%s',
            $onu->unique_external_id, $beforeDl, $beforeUl
        ));

        if ($dryRun) {
            $this->info("[CorregirOnu][DRY-RUN] Llamaría updateSpeedProfile({$onu->unique_external_id}, dl={$download}, ul={$upload})");
            $this->info('[CorregirOnu][DRY-RUN] Sin cambios aplicados.');
            return self::SUCCESS;
        }

        $service  = new OLTsService();

        $this->info('[CorregirOnu] Llamando updateSpeedProfile...');
        $response = $service->updateSpeedProfile($onu->unique_external_id, [
            'download_speed_profile_name' => $download,
            'upload_speed_profile_name'   => $upload,
        ]);

        if (!$response['success']) {
            $msg = $response['message'] ?? $response['error'] ?? 'SmartOLT devolvió error';
            $this->error("[CorregirOnu] FALLO SmartOLT: {$msg}");
            Log::error("[CorregirOnu] FALLO client_id={$clientId} ext={$onu->unique_external_id}: {$msg}");
            return self::FAILURE;
        }

        $this->info('[CorregirOnu] SmartOLT: OK. Sincronizando ONU...');
        $syncResult = $service->syncOnu($onu);

        if (!($syncResult['success'] ?? false)) {
            $this->error('[CorregirOnu] syncOnu falló tras updateSpeedProfile exitoso — revisar SmartOLT manualmente');
            Log::warning("[CorregirOnu] syncOnu falló para client_id={$clientId} tras update exitoso");
            return self::FAILURE;
        }

        $freshPorts = $syncResult['onu']->service_ports ?? [];
        $actualDl   = $freshPorts[0]['download_speed'] ?? 'N/A';
        $actualUl   = $freshPorts[0]['upload_speed']   ?? 'N/A';

        $this->info(sprintf(
            '[CorregirOnu] DESPUÉS — dl=%s  ul=%s',
            $actualDl, $actualUl
        ));

        if ($actualDl !== $download || $actualUl !== $upload) {
            $msg = sprintf(
                'MISMATCH post-corrección: ONU muestra dl=%s ul=%s — esperado dl=%s ul=%s',
                $actualDl, $actualUl, $download, $upload
            );
            $this->error("[CorregirOnu] {$msg}");
            Log::warning("[CorregirOnu] {$msg} | client_id={$clientId}");
            return self::FAILURE;
        }

        $this->info('[CorregirOnu] ✓ Corrección verificada correctamente.');
        Log::info(sprintf(
            '[CorregirOnu] OK client_id=%d ext=%s dl=%s ul=%s (antes: dl=%s ul=%s)',
            $clientId, $onu->unique_external_id, $actualDl, $actualUl, $beforeDl, $beforeUl
        ));

        return self::SUCCESS;
    }
}
