<?php

namespace App\Console\Commands\Active;

use App\Mail\BillingMail;
use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Modules\Addons\Payments\Models\ClientPaymentReference;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
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
                if (config('billing.whatsapp_fallback_enabled')) {
                    $phone = $this->resolveWhatsAppFallbackPhone($notif);

                    if ($phone) {
                        try {
                            $this->sendWhatsAppFallback($notif, $phone);
                            $notif->markSent();
                            $sent++;
                            $this->info("  ✔ Enviado (WhatsApp) notif#{$notif->id} ({$notif->document_type}) → {$phone}");
                            Log::info("[billing:send] notif#{$notif->id} sin email, enviado por respaldo WhatsApp a {$phone}.");
                        } catch (\Throwable $e) {
                            $msg = 'Respaldo WhatsApp falló: ' . substr($e->getMessage(), 0, 450);
                            $notif->markError($msg);
                            $errors++;
                            $this->error("  ✗ Error notif#{$notif->id}: {$msg}");
                            Log::error("[billing:send] notif#{$notif->id}: {$msg}");
                        }
                        continue;
                    }

                    $notif->markError('Sin correo ni teléfono registrado (sin canal de notificación)');
                    $errors++;
                    Log::warning("[billing:send] notif#{$notif->id} sin email ni teléfono (respaldo WhatsApp activo, sin canal).");
                    continue;
                }

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

    /**
     * Resuelve un teléfono utilizable para el respaldo por WhatsApp desde la
     * ficha del cliente (phone/phone2/phone3, primero no vacío con >=10 dígitos).
     * Devuelve el número ya normalizado (prefijo 521) o null si no hay ninguno.
     */
    private function resolveWhatsAppFallbackPhone(BillingNotification $notif): ?string
    {
        $cmi = $notif->client?->client_main_information;
        if (! $cmi) {
            return null;
        }

        foreach ([$cmi->phone, $cmi->phone2 ?? null, $cmi->phone3 ?? null] as $candidate) {
            $digits = preg_replace('/\D/', '', (string) $candidate);
            if (strlen($digits) >= 10) {
                return $this->normalizeWhatsAppNumber($digits);
            }
        }

        return null;
    }

    /** Mismo formato que usan las conversaciones de WhatsAppAgent (MX 10 dígitos → prefijo 521). */
    private function normalizeWhatsAppNumber(string $digits): string
    {
        if (strlen($digits) === 10) {
            return '521' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            return '521' . substr($digits, 2);
        }
        return $digits;
    }

    private function sendWhatsAppFallback(BillingNotification $notif, string $phone): void
    {
        app(WhatsAppGateway::class)->sendText(null, $phone, $this->buildWhatsAppFallbackMessage($notif), [
            'source'                   => 'billing_notification_fallback',
            'billing_notification_id'  => $notif->id,
        ]);
    }

    private function buildWhatsAppFallbackMessage(BillingNotification $notif): string
    {
        $cmi    = $notif->client?->client_main_information;
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));

        $tipo = match ($notif->document_type) {
            BillingNotification::TYPE_PREFACTURA => 'un aviso de cobro (prefactura) pendiente',
            BillingNotification::TYPE_RECIBO     => 'tu recibo de pago',
            BillingNotification::TYPE_TICKET     => 'un ticket de facturación',
            default                              => 'un documento de facturación pendiente',
        };

        $lineas = ['Hola' . ($nombre ? " {$nombre}" : '') . ", tienes {$tipo}."];

        if ($notif->invoice_id && ($invoice = $notif->invoice)) {
            $lineas[] = 'Monto: $' . number_format((float) $invoice->total, 2) . '.';
            if ($invoice->due_date) {
                $lineas[] = 'Fecha límite: ' . \Carbon\Carbon::parse($invoice->due_date)->format('d/m/Y') . '.';
            }
        } elseif ($notif->payment_id && ($payment = $notif->payment)) {
            $lineas[] = 'Monto: $' . number_format((float) $payment->amount, 2) . '.';
        }

        $reference = ClientPaymentReference::where('client_id', $notif->client_id)->value('reference');
        if ($reference) {
            $lineas[] = "Tu referencia de pago: {$reference}.";
        }

        $lineas[] = 'No te llegó por correo porque no tenemos uno registrado en tu ficha. Consulta el detalle con nuestro equipo de cobranza.';

        return implode(' ', $lineas);
    }
}
