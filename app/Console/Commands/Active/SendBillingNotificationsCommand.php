<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Hub\Services\Correo;
use App\Modules\Addons\Hub\Services\Servicios;
use App\Mail\BillingMail;
use App\Models\BillingConfig;
use App\Models\BillingNotification;
use App\Modules\Addons\Payments\Models\ClientPaymentReference;
use App\Models\BillingDocumentoEvidencia;
use App\Services\Finance\Billing\DocumentoNotificacionResolver;
use App\Services\Finance\Billing\EvidenciaDocumentoService;
use App\Services\Finance\Billing\ReactivacionEnvios;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Barre billing_notifications en estado en_espera cuya ventana ya venció
 * y las envía por correo (PDF adjunto). Idempotente: cada una se procesa en su
 * transacción con lockForUpdate para evitar doble envío en crons solapados.
 * Antes de enviar revalida el documento (DocumentoNotificacionResolver, FAC-04):
 * prefactura de una factura ya pagada o cancelada → se cancela; recibo de un pago
 * que dejó facturas pagadas → va la Factura (PAGADA) en su lugar. Reintento de SMTP: al fallar marca
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

        // Reactivación «en lotes por hora» (Configuración › Envíos automáticos): tope por hora
        // mientras quede lo acumulado; sin ritmo, null y todo igual que siempre.
        $ritmo = app(\App\Services\Finance\Billing\EnviosAutomaticos::class)->cupoPorRitmo();
        if ($ritmo !== null && ! $dryRun) {
            if ($ritmo <= 0) {
                $this->info('Ritmo por hora alcanzado (reactivación en lotes). Sigue en la siguiente hora.');
                return self::SUCCESS;
            }
            $limit = min($limit, $ritmo);
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

        // Una a una, cada una en su propia transacción con lockForUpdate: el candado dura hasta
        // terminar de enviar, así dos barridos solapados no mandan dos veces la misma (antes el
        // lockForUpdate iba sin transacción y no bloqueaba nada — FAC-04, item #509).
        $ids = (clone $query)->pluck('id');
        $resolver = app(DocumentoNotificacionResolver::class);
        $cancelled = 0;
        $fueraDeEtapa = 0;
        $etapa = app(ReactivacionEnvios::class);
        $enviadosHoy = BillingNotification::where('status', BillingNotification::STATUS_SENT)->whereDate('enviado_at', today())->count();
        $this->info('Reactivación (FAC-08): etapa ' . $etapa->etapa() . ', cupo de esta corrida ' . $etapa->cupo(0, $enviadosHoy) . '.');

        foreach ($ids as $id) {
            // FAC-08: segunda compuerta detrás de la pausa. Fuera de etapa = se queda en espera.
            if (! $dryRun && $etapa->cupo($sent, $enviadosHoy + $sent) <= 0) {
                $this->warn('  Parada: se alcanzó el límite de la etapa (por corrida o por día).');
                break;
            }
            if (! $dryRun && $etapa->pararPorErrores($errors)) {
                $this->warn("  Parada: {$errors} errores en esta corrida (límite de la etapa).");
                Log::warning("[billing:send] corrida detenida por errores ({$errors}).");
                break;
            }
            [$permitido, $motivoEtapa] = $etapa->permiteCliente(BillingNotification::whereKey($id)->value('client_id'));
            if (! $permitido) {
                $fueraDeEtapa++;
                if ($dryRun) {
                    $this->line("  [DRY-RUN] id={$id} queda en espera: {$motivoEtapa}");
                }
                continue;
            }

            $resultado = DB::transaction(function () use ($id, $dryRun, $resolver) {
                $notif = BillingNotification::where('id', $id)
                    ->where('status', BillingNotification::STATUS_WAITING)
                    ->where('send_by_email', true)
                    ->where('notificar_despues_de', '<=', now())
                    ->lockForUpdate()
                    ->first();

                if (! $notif) {
                    return null; // ya la procesó otro barrido o cambió de estado
                }

                // Revalidar JUSTO antes de enviar: la factura pudo pagarse o cancelarse durante
                // la ventana; el documento se resuelve con el mismo criterio que «Enviar».
                $decision = $resolver->resolver($notif, ! $dryRun);

                if ($dryRun) {
                    $this->line("  [DRY-RUN] id={$notif->id} type={$notif->document_type} email={$notif->email} → {$decision['accion']}: {$decision['motivo']}");

                    return null;
                }

                if ($decision['accion'] === DocumentoNotificacionResolver::CANCELAR) {
                    $notif->update(['status' => BillingNotification::STATUS_CANCELLED, 'error_log' => $decision['motivo']]);

                    return 'cancelada';
                }
                if ($decision['accion'] === DocumentoNotificacionResolver::ERROR) {
                    $notif->markError($decision['motivo']);
                    Log::error("[billing:send] notif#{$notif->id}: {$decision['motivo']}");

                    return 'error';
                }

                return $this->enviar($notif, $decision['documento']);
            });

            match ($resultado) {
                'enviada'   => $sent++,
                'error'     => $errors++,
                'cancelada' => $cancelled++,
                default     => null,
            };
        }

        if (! $dryRun) {
            $this->info("Resultado: {$sent} enviados, {$errors} errores, {$cancelled} cancelados al revalidar, {$fueraDeEtapa} fuera de etapa (siguen en espera).");
        }

        return self::SUCCESS;
    }

    /** Envía UNA notificación ya revalidada; devuelve 'enviada' o 'error'. */
    private function enviar(BillingNotification $notif, ?array $documento): string
    {
        if (empty($notif->email)) {
            if (app(ReactivacionEnvios::class)->permiteWhatsApp()) {
                $phone = $this->resolveWhatsAppFallbackPhone($notif);

                if ($phone) {
                    try {
                        $this->sendWhatsAppFallback($notif, $phone);
                        $notif->markSent();
                        $this->info("  ✔ Enviado (WhatsApp) notif#{$notif->id} ({$notif->document_type}) → {$phone}");
                        Log::info("[billing:send] notif#{$notif->id} sin email, enviado por respaldo WhatsApp a {$phone}.");

                        return 'enviada';
                    } catch (\Throwable $e) {
                        $msg = 'Respaldo WhatsApp falló: ' . substr($e->getMessage(), 0, 450);
                        $notif->markError($msg);
                        $this->error("  ✗ Error notif#{$notif->id}: {$msg}");
                        Log::error("[billing:send] notif#{$notif->id}: {$msg}");

                        return 'error';
                    }
                }

                $notif->markError('Sin correo ni teléfono registrado (sin canal de notificación)');
                Log::warning("[billing:send] notif#{$notif->id} sin email ni teléfono (respaldo WhatsApp activo, sin canal).");

                return 'error';
            }

            $notif->markError('Sin dirección de correo');
            Log::warning("[billing:send] notif#{$notif->id} sin email.");

            return 'error';
        }

        $evidencias = $this->registrarEvidencias($notif, $documento);

        try {
            Correo::enviar('facturacion.correo_recibos', $notif->email, new BillingMail($notif, $documento, $evidencias));
            $notif->markSent();
            $this->info("  ✔ Enviado notif#{$notif->id} ({$notif->document_type}) → {$notif->email}");

            return 'enviada';
        } catch (\Throwable $e) {
            $msg = substr($e->getMessage(), 0, 500);
            $notif->markError($msg);
            app(EvidenciaDocumentoService::class)->marcarFallido($evidencias, $msg);
            $this->error("  ✗ Error notif#{$notif->id}: {$msg}");
            Log::error("[billing:send] notif#{$notif->id}: {$msg}");

            return 'error';
        }
    }

    /**
     * FAC-05: guarda los bytes exactos de cada PDF que va en el correo (resuelto al enviar o, si
     * no, el PDF guardado de la notificación). @return int[] ids de evidencia
     */
    private function registrarEvidencias(BillingNotification $notif, ?array $documento): array
    {
        $adjuntos = $documento['adjuntos'] ?? [];
        if (! $adjuntos && $notif->pdf_path) {
            $disco = Storage::disk(config('billing.pdf_disk', 'local'));
            if ($disco->exists($notif->pdf_path)) {
                $adjuntos = [['data' => $disco->get($notif->pdf_path), 'name' => basename($notif->pdf_path)]];
            }
        }

        $servicio = app(EvidenciaDocumentoService::class);
        $ids = [];
        foreach ($adjuntos as $adjunto) {
            $e = $servicio->registrar($adjunto, [
                'origen'                  => BillingDocumentoEvidencia::ORIGEN_AUTOMATICO,
                'documento'               => $documento['tipo'] ?? $notif->document_type,
                'billing_notification_id' => $notif->id,
                'client_id'               => $notif->client_id,
                'invoice_id'              => $adjunto['invoice_id'] ?? $notif->invoice_id,
                'payment_id'              => $notif->payment_id,
                'destinatario'            => $notif->email,
                'asunto'                  => $documento['asunto'] ?? $notif->subject,
            ]);
            if ($e) {
                $ids[] = $e->id;
            }
        }

        return $ids;
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
        app(WhatsAppGateway::class)->sendText(Servicios::slugWhatsApp('facturacion.recordatorio'), $phone, $this->buildWhatsAppFallbackMessage($notif), [
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
