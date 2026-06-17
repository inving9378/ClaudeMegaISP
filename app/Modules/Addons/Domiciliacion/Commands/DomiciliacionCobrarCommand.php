<?php

namespace App\Modules\Addons\Domiciliacion\Commands;

use App\Mail\StandardMail;
use App\Models\Marketing\Setting;
use App\Models\Payment;
use App\Modules\Addons\Domiciliacion\Models\ClientRecurringCard;
use App\Modules\Addons\Domiciliacion\Models\RecurringChargeAttempt;
use App\Modules\Addons\Marketing\Services\EvolutionApiService;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use App\Modules\Addons\PortalCliente\Services\OpenpayTransactionException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DomiciliacionCobrarCommand extends Command
{
    protected $signature = 'domiciliacion:cobrar {--dry-run : Muestra qué se cobraría sin hacer ningún cargo real}';
    protected $description = 'Cobra automáticamente las facturas pendientes de clientes con domiciliación activa.';

    private const METHOD_ID_OPENPAY = 9;
    private const MAX_ATTEMPTS      = 3;
    private const ADD_BY_SYSTEM     = 0;

    // Estados que representan deuda activa. Verificado contra client_invoices en producción:
    // los únicos estados existentes son Pagado + estos 4. No existe "Archivado" en facturas.
    private const ESTADOS_CON_DEUDA = [
        'Pagar (del saldo de la cuenta)',
        'impagado',
        'Atrasado',
        'Partially paid',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // ── Verificación de compuertas ─────────────────────────────────────
        if (! config('openpay.sandbox')) {
            $this->error('BLOQUEADO: OPENPAY_SANDBOX debe ser true para correr este comando.');
            return self::FAILURE;
        }

        $habilitada = Setting::get('domiciliacion_habilitada') === 'true';
        if (! $habilitada && ! $dryRun) {
            $this->warn('Domiciliación apagada (domiciliacion_habilitada=false). Usa --dry-run para simular.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Simulación — no se harán cargos ni registros.');
        }

        // ── Encontrar candidatos ───────────────────────────────────────────
        // Clientes con tarjeta activa + exactamente 1 factura Atrasado
        // + sin intentos completados para esa factura + < MAX_ATTEMPTS fallidos
        $candidatos = $this->obtenerCandidatos();
        $this->info("Candidatos encontrados: " . count($candidatos));

        $exitosos  = 0;
        $fallidos  = 0;
        $omitidos  = 0;

        foreach ($candidatos as $candidato) {
            $resultado = $this->procesarCandidato($candidato, $dryRun);
            match ($resultado) {
                'ok'      => $exitosos++,
                'failed'  => $fallidos++,
                default   => $omitidos++,
            };
        }

        $this->table(
            ['Exitosos', 'Fallidos', 'Omitidos'],
            [[$exitosos, $fallidos, $omitidos]]
        );

        return self::SUCCESS;
    }

    private function obtenerCandidatos(): array
    {
        // 1. Todos los client_id con tarjeta activa
        $clientIdsConTarjeta = ClientRecurringCard::active()->pluck('client_id')->unique()->toArray();

        if (empty($clientIdsConTarjeta)) {
            return [];
        }

        $candidatos = [];

        foreach ($clientIdsConTarjeta as $clientId) {
            // 2. Solo facturas Atrasado (pendientes de pago del mes actual)
            $facturas = DB::table('client_invoices')
                ->where('client_id', $clientId)
                ->where('estado', 'Atrasado')
                ->whereNull('deleted_at')
                ->get(['id', 'number', 'total', 'estado', 'payment', 'document_date']);

            if ($facturas->count() !== 1) {
                // Más de 1 atrasada = no está al corriente, o ninguna = nada que cobrar
                continue;
            }

            $factura = $facturas->first();

            // 3. Verificar que no haya un intento completado para esta factura
            $yaCompletado = RecurringChargeAttempt::where('invoice_id', $factura->id)
                ->where('status', 'completed')
                ->exists();

            if ($yaCompletado) {
                continue;
            }

            // 4. Contar intentos fallidos: si ya alcanzó el máximo, saltar
            $intentosFallidos = RecurringChargeAttempt::where('invoice_id', $factura->id)
                ->where('status', 'failed')
                ->count();

            if ($intentosFallidos >= self::MAX_ATTEMPTS) {
                continue;
            }

            $card = ClientRecurringCard::forClient($clientId)->active()->first();
            if (! $card) {
                continue;
            }

            $cmi = DB::table('client_main_information')->where('client_id', $clientId)->first();

            $candidatos[] = [
                'client_id'    => $clientId,
                'cmi'          => $cmi,
                'factura'      => $factura,
                'card'         => $card,
                'attempt_no'   => $intentosFallidos + 1,
            ];
        }

        return $candidatos;
    }

    private function procesarCandidato(array $c, bool $dryRun): string
    {
        $clientId  = $c['client_id'];
        $factura   = $c['factura'];
        $card      = $c['card'];
        $cmi       = $c['cmi'];
        $attemptNo = $c['attempt_no'];
        $amount    = (float) $factura->total;

        $orderId = 'DOM-' . $clientId . '-' . $factura->id . '-' . $attemptNo . '-' . now()->format('Ym');

        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));

        if ($dryRun) {
            $this->line(sprintf(
                '  [DRY] Cliente %d (%s) — Factura #%s $%.2f — Intento %d/%d',
                $clientId, $nombre, $factura->number, $amount, $attemptNo, self::MAX_ATTEMPTS
            ));
            return 'dry';
        }

        // ── Registrar intento pending ─────────────────────────────────────
        $attempt = RecurringChargeAttempt::create([
            'client_id'         => $clientId,
            'invoice_id'        => $factura->id,
            'order_id'          => $orderId,
            'amount'            => $amount,
            'status'            => 'pending',
            'attempt_no'        => $attemptNo,
            'created_at'        => now(),
        ]);

        // ── Cargo en OpenPay ──────────────────────────────────────────────
        try {
            $svc   = app(OpenpayService::class);
            $cargo = $svc->cobrarTarjetaGuardada(
                customerId:  $card->openpay_customer_id,
                sourceId:    $card->openpay_card_id,
                amount:      $amount,
                orderId:     $orderId,
                description: "Mensualidad #{$factura->number} — cliente #{$clientId}"
            );
        } catch (OpenpayTransactionException $e) {
            $this->registrarFallo($attempt, $card, $factura, $attemptNo, $cmi, $e->getOpenpayCode(), $e->getMessage());
            $this->warn("  [FAIL] Cliente {$clientId} ({$nombre}) — {$e->getMessage()} (código {$e->getOpenpayCode()})");
            return 'failed';
        } catch (\Throwable $e) {
            $this->registrarFallo($attempt, $card, $factura, $attemptNo, $cmi, 0, $e->getMessage());
            $this->warn("  [ERROR] Cliente {$clientId} ({$nombre}) — {$e->getMessage()}");
            return 'failed';
        }

        if (($cargo->status ?? '') !== 'completed') {
            $this->registrarFallo($attempt, $card, $factura, $attemptNo, $cmi, 0, 'Estado inesperado: ' . ($cargo->status ?? 'null'));
            return 'failed';
        }

        // ── Cargo exitoso — registrar pago y acreditar balance ────────────
        $chargeId = $cargo->id;
        try {
            DB::transaction(function () use ($clientId, $factura, $chargeId, $amount, $attempt) {
                $payment = Payment::create([
                    'payment_method_id'       => self::METHOD_ID_OPENPAY,
                    'date'                    => now()->toDateTimeString(),
                    'amount'                  => $amount,
                    'payment_period'          => '',
                    'comment'                 => 'Cobro automático mensual (Domiciliación)',
                    'receipt'                 => $chargeId,
                    'send_receipt_after_payment' => false,
                    'add_by'                  => self::ADD_BY_SYSTEM,
                    'paymentable_id'          => $clientId,
                    'paymentable_type'        => 'App\\Models\\Client',
                    'is_first_payment'        => false,
                    'enabled_payment_promise' => false,
                ]);

                // Actualizar factura como pagada
                DB::table('client_invoices')
                    ->where('id', $factura->id)
                    ->update([
                        'estado'       => 'Pagado',
                        'payment_date' => now()->format('d/m/Y'),
                        'payment'      => $factura->payment
                            ? trim($factura->payment) . ', ' . $payment->id
                            : (string) $payment->id,
                        'updated_at'   => now(),
                    ]);

                // Marcar intento como completado
                $attempt->update([
                    'status'           => 'completed',
                    'openpay_charge_id' => $chargeId,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('domiciliacion.cobrar: error al registrar pago exitoso', [
                'client_id'  => $clientId,
                'invoice_id' => $factura->id,
                'charge_id'  => $chargeId,
                'error'      => $e->getMessage(),
            ]);
            // El cargo ya sucedió en OpenPay — no marcamos fallo, necesita revisión manual
            $this->error("  [CRITICO] Cargo {$chargeId} OK pero fallo al registrar pago. Revisar manualmente.");
            return 'failed';
        }

        $this->notificarExito($cmi, $factura, $amount, $chargeId);
        $this->info("  [OK] Cliente {$clientId} ({$nombre}) — ${$amount} — cargo {$chargeId}");

        // Marcar notificación enviada
        $attempt->update(['notified_at' => now()]);

        return 'ok';
    }

    private function registrarFallo(
        RecurringChargeAttempt $attempt,
        ClientRecurringCard    $card,
        object                 $factura,
        int                    $attemptNo,
        ?object                $cmi,
        int                    $errorCode,
        string                 $errorMessage
    ): void {
        $attempt->update([
            'status'        => 'failed',
            'error_code'    => $errorCode,
            'error_message' => $errorMessage,
        ]);

        // Último intento → marcar tarjeta como fallida
        if ($attemptNo >= self::MAX_ATTEMPTS) {
            $card->update(['status' => 'failed']);
            $this->notificarFalloFinal($cmi, $factura, $errorMessage);
            $attempt->update(['notified_at' => now()]);
        } else {
            $this->notificarFalloIntento($cmi, $factura, $attemptNo, $errorMessage);
            $attempt->update(['notified_at' => now()]);
        }

        Log::warning('domiciliacion.cobrar: fallo en cobro', [
            'client_id'   => $card->client_id,
            'invoice_id'  => $factura->id,
            'attempt_no'  => $attemptNo,
            'error_code'  => $errorCode,
            'error'       => $errorMessage,
        ]);
    }

    // ── Notificaciones ──────────────────────────────────────────────────────

    private function notificarExito(?object $cmi, object $factura, float $amount, string $chargeId): void
    {
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $msg    = "✅ Hola {$nombre}, tu pago de \${$amount} MXN para la factura #{$factura->number} fue procesado exitosamente. Referencia: {$chargeId}.";
        $html   = "<p>Hola {$nombre},</p><p>Tu cobro automático mensual de <strong>\${$amount} MXN</strong> para la factura <strong>#{$factura->number}</strong> fue procesado exitosamente.</p><p>Referencia: <code>{$chargeId}</code></p>";

        $this->enviarWhatsApp($cmi, $msg);
        $this->enviarEmail($cmi, '✅ Pago automático procesado — Meganet', $html);
        $this->llamarSoft($cmi);
    }

    private function notificarFalloIntento(?object $cmi, object $factura, int $attemptNo, string $error): void
    {
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $msg    = "⚠️ Hola {$nombre}, no pudimos cobrar tu factura #{$factura->number} (intento {$attemptNo}/3). Reintentaremos mañana. Razón: {$error}";
        $html   = "<p>Hola {$nombre},</p><p>No pudimos procesar tu cobro automático para la factura <strong>#{$factura->number}</strong> (intento {$attemptNo}/3). Lo intentaremos de nuevo mañana.</p><p>Motivo: {$error}</p>";

        $this->enviarWhatsApp($cmi, $msg);
        $this->enviarEmail($cmi, '⚠️ Intento de cobro fallido — Meganet', $html);
    }

    private function notificarFalloFinal(?object $cmi, object $factura, string $error): void
    {
        $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));
        $msg    = "❌ Hola {$nombre}, después de 3 intentos no pudimos cobrar tu factura #{$factura->number}. Tu domiciliación quedó desactivada. Comunícate con soporte.";
        $html   = "<p>Hola {$nombre},</p><p>Después de 3 intentos fallidos, no fue posible procesar tu cobro automático para la factura <strong>#{$factura->number}</strong>.</p><p>Tu domiciliación quedó desactivada. Por favor comunícate con nosotros.</p>";

        $this->enviarWhatsApp($cmi, $msg);
        $this->enviarEmail($cmi, '❌ Cobro automático desactivado — Meganet', $html);
        $this->llamarSoft($cmi);
    }

    private function enviarWhatsApp(?object $cmi, string $mensaje): void
    {
        try {
            $phone = $cmi->phone ?? '';
            if (empty(preg_replace('/\D/', '', $phone))) {
                return;
            }
            $jid = EvolutionApiService::phoneToJid($phone);
            app(EvolutionApiService::class)->sendText($jid, $mensaje);
        } catch (\Throwable $e) {
            Log::warning('domiciliacion.cobrar: fallo WhatsApp notificación', ['error' => $e->getMessage()]);
        }
    }

    private function enviarEmail(?object $cmi, string $asunto, string $html): void
    {
        try {
            $email = $cmi->email ?? null;
            if (! $email) {
                return;
            }
            Mail::send(new StandardMail(['to' => $email, 'title' => $asunto, 'body' => $html]));
        } catch (\Throwable $e) {
            Log::warning('domiciliacion.cobrar: fallo email notificación', ['error' => $e->getMessage()]);
        }
    }

    private function llamarSoft(?object $cmi): void
    {
        // Soft fail: la llamada AMI es best-effort; no bloquea el flujo.
        try {
            $phone = $cmi->phone ?? '';
            if (empty(preg_replace('/\D/', '', $phone))) {
                return;
            }
            // AmiConnectionService es opcional — si no está disponible, silencioso.
            if (class_exists(\App\Modules\Addons\VoIP\Services\AmiConnectionService::class)) {
                app(\App\Modules\Addons\VoIP\Services\AmiConnectionService::class)
                    ->originateCall($phone, 'Cobro automático Meganet');
            }
        } catch (\Throwable $e) {
            Log::info('domiciliacion.cobrar: llamada soft-fail', ['error' => $e->getMessage()]);
        }
    }
}
