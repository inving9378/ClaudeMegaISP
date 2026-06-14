<?php

namespace App\Modules\Addons\GestionRed\Services;

use App\Models\GeneralNotification;
use App\Models\OltSmartoltConfig;
use App\Models\User;
use App\Notifications\StandardNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Servicio de alertas para interrupciones PON detectadas por SmartOLT.
 *
 * Modo de operación:
 *   - alertas_olt_activas = false (default) → DRY-RUN: solo loguea, NUNCA envía.
 *   - alertas_olt_activas = true           → Envía notificaciones reales al rol destino.
 *
 * Irving debe activar el flag manualmente después de validar las alertas en el log.
 */
class OltAlertService
{
    private bool $dryRun;
    private ?string $rolDestino;

    public function __construct()
    {
        $cfg = OltSmartoltConfig::current();
        // El flag actúa como dry-run invertido: apagado → no envía
        $this->dryRun     = !($cfg->alertas_olt_activas ?? false);
        $this->rolDestino = $cfg->alertas_rol_destino ?? null;
    }

    /**
     * Evalúa los PONs de interrupción actuales y dispara alertas si corresponde.
     * Retorna el número de alertas generadas (o que se habrían generado en dry-run).
     */
    public function procesarInterrupciones(): int
    {
        $pons = $this->getInterrupcionesPon();

        if ($pons->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($pons as $pon) {
            $this->procesarPon($pon);
            $count++;
        }

        if ($this->dryRun) {
            Log::channel('daily')->info(
                "[OltAlerts DRY-RUN] {$count} interrupción(es) PON detectadas. " .
                "Sin envío — activa alertas_olt_activas para enviar notificaciones reales."
            );
        }

        return $count;
    }

    /**
     * Devuelve todos los PONs marcados como con interrupción actual.
     * Criterio: total_onus > 0 y los_count > 0 (hay ONUs con LOS = pérdida de señal).
     */
    private function getInterrupcionesPon(): Collection
    {
        return DB::table('olt_interruption_pons as p')
            ->join('olts as o', 'o.id', '=', 'p.olt_id')
            ->where('p.los_count', '>', 0)
            ->select(
                'p.id', 'p.olt_id', 'p.board', 'p.port',
                'p.cause', 'p.total_onus', 'p.los_count',
                'p.latest_status_change', 'p.pon_description',
                'o.name as olt_name', 'o.ip as olt_ip'
            )
            ->get();
    }

    private function procesarPon(object $pon): void
    {
        $titulo   = "Interrupción PON: {$pon->olt_name} — Puerto {$pon->board}/{$pon->port}";
        $detalle  = "Causa: {$pon->cause} | ONUs afectadas: {$pon->los_count}/{$pon->total_onus}";
        $code     = "olt-pon-interrupt-{$pon->olt_id}-{$pon->board}-{$pon->port}";

        if ($this->dryRun) {
            Log::channel('daily')->warning(
                "[OltAlerts DRY-RUN] Alerta que SE ENVIARÍA: {$titulo} | {$detalle}"
            );
            return;
        }

        // ── Envío real ────────────────────────────────────────────────────────
        $notification = new GeneralNotification();
        $notification->priority    = 'Alta';
        $notification->title       = $titulo;
        $notification->description = $detalle;
        $notification->code        = $code;
        $notification->base_url    = '/olts';
        $notification->model       = 'OltInterruptionPon';
        $notification->model_id    = $pon->id;
        $notification->save();

        $users = $this->resolveDestinationUsers();

        if ($users->isEmpty()) {
            Log::warning("[OltAlerts] No hay usuarios destino para la alerta PON {$code}");
            return;
        }

        Notification::send($users, new StandardNotification($notification));

        Log::info("[OltAlerts] Alerta enviada: {$titulo} → {$users->count()} usuario(s)");
    }

    /**
     * Resuelve qué usuarios reciben las alertas:
     * - Si alertas_rol_destino está configurado → usuarios con ese rol.
     * - Si está vacío → usuarios admin (User::admin()).
     */
    private function resolveDestinationUsers(): Collection
    {
        if ($this->rolDestino) {
            return User::role($this->rolDestino)->get();
        }
        return User::admin()->get();
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
