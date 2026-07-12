<?php

namespace App\Modules\Addons\Payments\Services\Conciliation;

use App\Models\User;
use App\Modules\Addons\Payments\Models\ReportedPayment;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use App\Modules\Addons\Payments\Services\PaymentApplicationService;
use App\Modules\Core\Clientes\Models\Client;
use App\Services\Finance\Invoice\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FASE 4 (F4.3) — Aplica el pago de una sesión de identificación resuelta.
 *
 * REUSA applyPayment (Paso 2): abona saldo idéntico a cualquier pago. add_by =
 * MEGAISP. Crea el reported_payment de traza (identified_by/confirmed_by) y
 * marca la sesión como aplicada.
 *
 * ⚠️ MUEVE DINERO. Tres candados anti-duplicado + freno maestro:
 *   1) clave_rastreo única (no re-aplicar el mismo comprobante ni el doble webhook);
 *   2) claim atómico de la sesión (applied_at null→now, gana uno solo);
 *   3) (en F3) sesión única por mensaje.
 *   + PAYMENTS_AUTO_APPLY_ENABLED: con false, la vía automática NO aplica.
 *
 * @return array {applied, reason, payment_id?, reported_payment_id?}
 */
class PaymentFromSessionService
{
    private const METHOD_TRANSFERENCIA = 2; // method_of_payments: Transferencia Bancaria (SPEI)

    public function __construct(
        private PaymentApplicationService $payments,
        private InvoiceService $invoices
    ) {}

    /**
     * FASE 6 — Punto de entrada para la confirmación humana (Tere). Aplica el
     * pago de una sesión 'proposed' (o multi-servicio) que un humano confirma.
     * NO lo frena el freno maestro (es acción humana deliberada). Registra
     * confirmed_by = el usuario que confirma.
     */
    public function applyConfirmed(int $sessionId, int $tereUserId): array
    {
        $session = Session::find($sessionId);
        if (!$session) {
            return $this->blocked('session_not_found');
        }
        return $this->apply($session, $tereUserId);
    }

    /**
     * @param Session  $session
     * @param int|null $confirmedBy  usuario humano que confirma (Tere). null = vía AUTOMÁTICA.
     */
    public function apply(Session $session, ?int $confirmedBy = null): array
    {
        $automatic = $confirmedBy === null;

        // ── Pre-condiciones (sin tocar dinero) ──────────────────────────────
        if ($session->is_simulation) {
            return $this->blocked('simulation'); // las simulaciones NUNCA aplican
        }
        if ($session->state !== Session::STATE_RESOLVED || !$session->resolved_client_id) {
            return $this->blocked('not_resolved');
        }
        if ($session->applied_at !== null) {
            return $this->blocked('already_applied'); // candado 2 (rápido)
        }

        // Ruteo (independiente del freno maestro): 'proposed' y multi-servicio
        // SIEMPRE van a confirmación humana (Tere), aunque el auto-apply esté off.
        if ($automatic && !$session->isAutoApplicable()) {
            return $this->blocked('needs_human_confirmation'); // proposed (nombre/calle)
        }
        if ($automatic && $session->resolved_multiple_services) {
            return $this->blocked('multiple_services_needs_human');
        }

        // FRENO MAESTRO: solo aquí (exact + auto-elegible). Con flag off, el
        // candidato exacto NO se aplica y espera (no molesta a Tere).
        if ($automatic && !\App\Modules\Addons\Payments\Support\ConciliationSettings::enabled('auto_apply_enabled')) {
            Log::channel('evolution')->info('F4: auto-apply FRENADO (flag off) — candidato exact en espera', [
                'session_id' => $session->id, 'client_id' => $session->resolved_client_id,
            ]);
            return $this->blocked('auto_apply_disabled');
        }

        // Datos del comprobante (monto + clave). Sin comprobante/monto → no aplica.
        $data = $this->extractionData($session);
        if ($data['amount'] === null || $data['amount'] <= 0) {
            return $this->blocked('no_amount');
        }

        // Candado 1: no re-aplicar el mismo comprobante. Identificador anti-duplicado:
        // la clave de rastreo (o referencia) si la hay — más fuerte; si NO hay, una
        // huella (monto + fecha + banco). Ya NO se bloquea por falta de clave: un
        // comprobante legítimo sin clave (efectivo, algunos Mercado Pago) SÍ se aplica.
        $clave       = $data['clave']; // clave_rastreo o referencia; puede ser null
        $fingerprint = $clave ? null : $this->paymentFingerprint($data);

        if ($clave) {
            if ($this->claveAlreadyUsed($clave)) {
                return $this->blocked('duplicate_clave');
            }
        } elseif ($fingerprint !== null && $this->fingerprintAlreadyUsed($fingerprint)) {
            return $this->blocked('duplicate_fingerprint');
        }
        // Si no hay clave y la huella es demasiado débil (solo monto) → se permite
        // aplicar sin dedup, para no bloquear un pago legítimo.

        // Candado 2: claim ATÓMICO de la sesión (gana un solo proceso).
        $claimed = Session::where('id', $session->id)->whereNull('applied_at')
            ->update(['applied_at' => now()]);
        if ($claimed !== 1) {
            return $this->blocked('already_applied'); // otro proceso ganó
        }

        // ── Aplicación (dinero) ─────────────────────────────────────────────
        try {
            $megaisp = User::systemBot()?->id ?? 1;

            // Trazabilidad: dejar claro si se identificó por clave/ref o por huella.
            $idLabel = $clave
                ? 'clave/ref: ' . $clave
                : 'SIN clave de rastreo; identificado por huella monto/fecha/banco';

            $payment = $this->payments->applyPayment([
                'client_id'   => $session->resolved_client_id,
                'amount'      => $data['amount'],
                'method_id'   => self::METHOD_TRANSFERENCIA,
                'add_by'      => $megaisp,
                'external_id' => $clave, // null si el comprobante no trae clave/ref
                'provider'    => 'whatsapp',
                'comment'     => 'Pago por WhatsApp (conciliación IA, ' . $idLabel . ')',
            ]);

            // Item #194(b) — regresión del fix (a): applyPayment abona el balance
            // pero deja la factura de adeudo ABIERTA. El mostrador la salda vía
            // InvoiceService (period-based); conciliación/SPEI no traen period →
            // saldar por deuda VIVA más antigua (FIFO). Best-effort: un fallo aquí
            // NUNCA revierte el pago ya aplicado (mismo patrón que notifyApplied).
            try {
                $clienteDeuda = Client::find($session->resolved_client_id);
                if ($clienteDeuda) {
                    $this->invoices->saldarDeudaVivaFifo($clienteDeuda, $payment);
                }
            } catch (\Throwable $e) {
                Log::channel('evolution')->warning('F4: saldarDeudaVivaFifo falló (pago sí aplicado): ' . $e->getMessage(), [
                    'session_id' => $session->id, 'payment_id' => $payment->id,
                ]);
            }

            $reported = ReportedPayment::create([
                'payment_id'                => $payment->id,
                'client_id'                 => $session->resolved_client_id,
                'method_of_payment_id'      => self::METHOD_TRANSFERENCIA,
                'amount'                    => $data['amount'],
                'fecha_pago'                => $data['fecha'] ?? now()->toDateString(),
                'clave_rastreo'             => $clave,
                'dedup_fingerprint'         => $clave ? null : $fingerprint,
                'titular'                   => $data['titular'] ?? null,
                'banco_origen'              => $data['banco'] ?? null,
                'comprobante_path'          => $data['comprobante_path'] ?? null,
                'conciliation_status'       => ReportedPayment::ESTADO_PENDIENTE ?? 'pendiente_verificar',
                'identified_by_user_id'     => $megaisp,
                'confirmed_by_user_id'      => $confirmedBy,
                'identification_session_id' => $session->id,
            ]);

            $session->update(['applied_payment_id' => $payment->id]);

            Log::channel('evolution')->info('F4: pago aplicado', [
                'session_id'  => $session->id,
                'payment_id'  => $payment->id,
                'client_id'   => $session->resolved_client_id,
                'amount'      => $data['amount'],
                'automatic'   => $automatic,
                'confirmed_by'=> $confirmedBy,
            ]);

            // (b) Confirmación al cliente por WhatsApp — SOLO aquí, en el éxito REAL.
            // Respeta wa_autorespond y el estado del cliente. Best-effort: un fallo
            // al notificar NUNCA revierte el pago ya aplicado.
            try {
                app(\App\Modules\Addons\Payments\Services\Conciliation\ConciliationResponder::class)
                    ->notifyApplied($session->fresh());
            } catch (\Throwable $e) {
                Log::channel('evolution')->warning('F4: notifyApplied falló (pago sí aplicado): ' . $e->getMessage());
            }

            return [
                'applied'              => true,
                'reason'               => $automatic ? 'auto' : 'confirmed',
                'payment_id'           => $payment->id,
                'reported_payment_id'  => $reported->id,
            ];
        } catch (\Throwable $e) {
            // Falló la aplicación → LIBERAR el claim para permitir reintento.
            Session::where('id', $session->id)->update(['applied_at' => null]);
            Log::channel('evolution')->error('F4: aplicación falló, claim liberado: ' . $e->getMessage(), [
                'session_id' => $session->id,
            ]);
            return $this->blocked('apply_error:' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function extractionData(Session $session): array
    {
        $out = ['amount' => null, 'clave' => null, 'fecha' => null, 'fecha_pago_raw' => null, 'titular' => null, 'banco' => null, 'comprobante_path' => null];
        if (!$session->extraction_id) {
            return $out;
        }
        $ext = DB::table('whatsapp_payment_extractions')->where('id', $session->extraction_id)->first();
        if (!$ext) {
            return $out;
        }
        $f = $ext->fields ? json_decode($ext->fields, true) : [];
        $val = fn ($k) => $f[$k]['value'] ?? null;

        $monto = $val('monto');
        $out['amount']  = ($monto !== null && is_numeric(str_replace(',', '', (string) $monto)))
            ? (float) str_replace(',', '', (string) $monto) : null;
        // Clave: clave de rastreo SPEI (fuerte); si no hay, la referencia de operación.
        $clave = $val('clave_rastreo');
        if ($clave === null || trim((string) $clave) === '') {
            $clave = $val('referencia');
        }
        $out['clave']          = ($clave !== null && trim((string) $clave) !== '') ? trim((string) $clave) : null;
        $out['fecha']          = null; // la fecha del comprobante es texto libre; se usa la de hoy en applyPayment
        $out['fecha_pago_raw'] = $val('fecha_pago'); // texto crudo, solo para la huella anti-duplicado
        $out['titular']        = $val('titular_ordenante');
        $out['banco']          = $val('banco_origen');

        // Ruta del comprobante desde el mensaje.
        $out['comprobante_path'] = DB::table('marketing_messages')->where('id', $ext->message_id)->value('media_path');

        return $out;
    }

    /** Candado 1: ¿ya existe un pago o reported_payment con esta clave? */
    private function claveAlreadyUsed(string $clave): bool
    {
        $inReported = DB::table('reported_payments')->where('clave_rastreo', $clave)->whereNull('deleted_at')->exists();
        $inPayments = DB::table('payments')->where('number', $clave)->whereNull('deleted_at')->exists();
        return $inReported || $inPayments;
    }

    /**
     * Huella anti-duplicado para comprobantes SIN clave: monto + fecha + banco.
     * Devuelve null si es demasiado débil (solo monto, sin fecha ni banco) para
     * NO bloquear pagos legítimos por una coincidencia de monto.
     */
    private function paymentFingerprint(array $data): ?string
    {
        $amount = $data['amount'];
        $fecha  = $this->norm($data['fecha_pago_raw'] ?? null);
        $banco  = $this->norm($data['banco'] ?? null);
        if ($amount === null || ($fecha === '' && $banco === '')) {
            return null;
        }
        return 'FP:' . number_format((float) $amount, 2, '.', '') . '|' . $fecha . '|' . $banco;
    }

    /** ¿Ya se aplicó antes un pago con esta misma huella? */
    private function fingerprintAlreadyUsed(string $fingerprint): bool
    {
        return DB::table('reported_payments')
            ->where('dedup_fingerprint', $fingerprint)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function norm(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower((string) $s)));
    }

    private function blocked(string $reason): array
    {
        return ['applied' => false, 'reason' => $reason];
    }
}
