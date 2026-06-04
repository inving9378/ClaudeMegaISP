<?php

namespace App\Console\Commands\Active;

use App\Mail\BillingMail;
use App\Models\BillingConfig;
use App\Models\BillingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Barre billing_notifications en estado en_espera cuya ventana ya venció
 * y las envía por correo (PDF adjunto). Idempotente: usa lockForUpdate para
 * evitar doble envío en crons solapados. Reintento de SMTP: al fallar marca
 * 'error' con log; el siguiente barrido la ignorará (status != en_espera).
 *
 * Schedule: cada 15 minutos.
 */
class SendBillingNotificationsCommand extends Command
{
    protected $signature = 'billing:send-pending-notifications
                            {--dry-run : Muestra qué se enviaría sin enviar}
                            {--limit=100 : Máximo de registros por ejecución}';

    protected $description = 'Envía notificaciones de billing (prefactura/recibo) cuya ventana de retención venció';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if (BillingConfig::current()->envio_pausado && ! $dryRun) {
            $this->info('Envío de notificaciones PAUSADO (billing_config.envio_pausado=true). Los documentos siguen generándose.');
            return self::SUCCESS;
        }

        $this->info("Buscando notificaciones pendientes (dry-run=" . ($dryRun ? 'si' : 'no') . ", limit={$limit})...");

        // Solo filas listas: en_espera + send_by_email + ventana vencida
        $query = BillingNotification::pendingToSend()->limit($limit);
        $total = $query->count();

        if ($total === 0) {
            $this->info('Sin notificaciones pendientes.');
            return self::SUCCESS;
        }

        $this->info("Encontradas: {$total}");

        $sent   = 0;
        $errors = 0;

        // Procesar una a una con lockForUpdate para evitar doble envío en crons solapados
        $ids = (clone $query)->pluck('id');

        foreach ($ids as $id) {
            $notif = BillingNotification::where('id', $id)
                ->where('status', BillingNotification::STATUS_WAITING)
                ->where('send_by_email', true)
                ->where('notificar_despues_de', '<=', now())
                ->lockForUpdate()
                ->first();

            if (! $notif) {
                // Ya fue procesada por otro worker o cambió de estado
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY-RUN] id={$notif->id} type={$notif->document_type} email={$notif->email}");
                continue;
            }

            if (empty($notif->email)) {
                $notif->markError('Sin dirección de correo');
                $errors++;
                Log::warning("[billing:send] notif#{$notif->id} sin email.");
                continue;
            }

            try {
                Mail::to($notif->email)->send(new BillingMail($notif));
                $notif->markSent();
                $sent++;
                $this->info("  ✔ Enviado notif#{$notif->id} ({$notif->document_type}) → {$notif->email}");
            } catch (\Throwable $e) {
                $msg = substr($e->getMessage(), 0, 500);
                $notif->markError($msg);
                $errors++;
                $this->error("  ✗ Error notif#{$notif->id}: {$msg}");
                Log::error("[billing:send] notif#{$notif->id}: {$msg}");
            }
        }

        if (! $dryRun) {
            $this->info("Resultado: {$sent} enviados, {$errors} errores.");
        }

        return self::SUCCESS;
    }
}
