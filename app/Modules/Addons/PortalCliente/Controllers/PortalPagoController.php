<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use App\Modules\Addons\PortalCliente\Services\OpenpayTransactionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cargo síncrono con tarjeta tokenizada vía OpenPay.
 *
 * Flujo:
 *   1. Navegador tokeniza PAN con openpay.js → envía solo token + device_session_id
 *   2. Este controller valida scope, obtiene monto de la factura (nunca del request),
 *      registra el intento en portal_payment_attempts y llama a OpenpayService::cobrarTarjeta
 *   3. Si el cargo es `completed` → escribe en payments + actualiza client_invoices
 *   4. Si falla → marca el intento y responde con mensaje amigable
 *
 * Seguridad:
 *   - invoice_id siempre se scoping al cliente autenticado (abort 404 si es ajena)
 *   - El monto proviene de client_invoices.total, nunca del request
 *   - La private_key solo vive en OpenpayService (backend, desde config)
 *   - Idempotencia: un intento `pending` activo bloquea nuevos envíos
 */
class PortalPagoController extends Controller
{
    private const METHOD_ID_OPENPAY = 9;  // id en method_of_payments: "Tarjeta OpenPay"
    private const ADD_BY_PORTAL     = 0;  // pagos insertados por el portal (no por un admin)

    public function cobrar(Request $request, int $invoiceId): JsonResponse
    {
        // ── Validación básica del payload ──────────────────────────────
        $data = $request->validate([
            'token'             => 'required|string|max:100',
            'device_session_id' => 'required|string|max:255',
        ]);

        // ── Scope: la factura DEBE pertenecer al cliente autenticado ───
        $cmi      = Auth::guard('cliente')->user();
        $clientId = $cmi->client_id;

        $factura = DB::table('client_invoices')
            ->where('id', $invoiceId)
            ->where('client_id', $clientId)
            ->first(['id', 'number', 'total', 'estado', 'payment']);

        if (! $factura) {
            return response()->json(['success' => false, 'error' => 'Factura no encontrada.'], 404);
        }

        // ── Verificar que la factura no esté ya pagada ─────────────────
        if ($this->estaFacuraPagada($factura->estado)) {
            return response()->json([
                'success' => false,
                'error'   => 'Esta factura ya fue pagada. Actualiza la página.',
            ]);
        }

        // ── Idempotencia: bloquear si hay un intento `pending` activo ──
        // (evita doble clic o doble submit)
        $intentoPendiente = DB::table('portal_payment_attempts')
            ->where('invoice_id', $invoiceId)
            ->where('client_id',  $clientId)
            ->where('status',     'pending')
            ->where('created_at', '>=', now()->subSeconds(30))
            ->exists();

        if ($intentoPendiente) {
            return response()->json([
                'success' => false,
                'error'   => 'Tu pago está siendo procesado. Espera un momento.',
            ]);
        }

        // ── Registrar el intento ANTES de llamar a OpenPay ─────────────
        $orderId = 'pp-' . $clientId . '-' . $invoiceId . '-' . Str::random(10);

        $attemptId = DB::table('portal_payment_attempts')->insertGetId([
            'client_id'  => $clientId,
            'invoice_id' => $invoiceId,
            'order_id'   => $orderId,
            'amount'     => $factura->total,
            'status'     => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── IP real del cliente para X-Forwarded-For (antifraude) ───────
        // nginx en producción setea X-Forwarded-For; en sandbox acepta cualquier IP válida
        $clientIp = $request->header('X-Forwarded-For')
            ? explode(',', $request->header('X-Forwarded-For'))[0]
            : $request->ip();

        // Sustituir cualquier IP privada/reservada/loopback por una IP pública de fallback.
        // OpenPay rechaza IPs privadas en el campo X-Forwarded-For para antifraude.
        // FILTER_FLAG_NO_PRIV_RANGE cubre: 192.168.x.x, 10.x.x.x, 172.16-31.x.x, fe80::...
        // FILTER_FLAG_NO_RES_RANGE cubre: 127.x.x.x, ::1, 0.x.x.x, 240+.x.x.x, etc.
        if (! filter_var(trim($clientIp), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $clientIp = '201.144.0.1'; // IP pública de fallback (sandbox/LAN)
        }

        // ── Llamada a OpenPay ─────────────────────────────────────────
        try {
            $svc = app(OpenpayService::class);

            $cargo = $svc->cobrarTarjeta(
                token           : $data['token'],
                deviceSessionId : $data['device_session_id'],
                amount          : (float) $factura->total,
                orderId         : $orderId,
                description     : "Factura #{$factura->number} — cliente #{$clientId}",
                customer        : [
                    'name'         => $cmi->name ?? 'Cliente',
                    'last_name'    => $cmi->father_last_name ?? '',
                    'email'        => $cmi->email ?? '',
                    'phone_number' => $cmi->phone ?? '',
                ],
                clientIp        : trim($clientIp)
            );

        } catch (OpenpayTransactionException $e) {
            // Tarjeta rechazada, fondos insuficientes, fraude, etc.
            DB::table('portal_payment_attempts')
                ->where('id', $attemptId)
                ->update([
                    'status'        => 'failed',
                    'error_code'    => $e->getOpenpayCode(),
                    'error_message' => $e->getMessage(),
                    'updated_at'    => now(),
                ]);

            return response()->json([
                'success'    => false,
                'error'      => $e->getMessage(),
                'error_code' => $e->getOpenpayCode(),
            ]);
        } catch (\Throwable $e) {
            DB::table('portal_payment_attempts')
                ->where('id', $attemptId)
                ->update([
                    'status'        => 'failed',
                    'error_code'    => 0,
                    'error_message' => 'Error inesperado: ' . $e->getMessage(),
                    'updated_at'    => now(),
                ]);

            return response()->json([
                'success' => false,
                'error'   => 'Ocurrió un error al procesar el pago. Intenta de nuevo.',
            ]);
        }

        // ── Procesar resultado del cargo ───────────────────────────────
        $chargeId = $cargo->id ?? null;
        $status   = $cargo->status ?? 'unknown';

        // Actualizar el intento con el ID de OpenPay
        DB::table('portal_payment_attempts')
            ->where('id', $attemptId)
            ->update([
                'openpay_charge_id' => $chargeId,
                'status'            => $status === 'completed' ? 'completed' : 'failed',
                'updated_at'        => now(),
            ]);

        if ($status === 'completed') {
            // ── Registrar el pago real en la estructura existente ──────
            $this->registrarPago($clientId, $factura, $chargeId, (float) $factura->total);

            return response()->json([
                'success'      => true,
                'charge_id'    => $chargeId,
                'message'      => '¡Pago exitoso! Tu factura ha sido actualizada.',
            ]);
        }

        // Si el cargo no quedó `completed` pero tampoco lanzó excepción
        // (raro en tarjeta síncrona — podría ser `in_progress` en SPEI)
        DB::table('portal_payment_attempts')
            ->where('id', $attemptId)
            ->update(['status' => 'failed', 'updated_at' => now()]);

        return response()->json([
            'success' => false,
            'error'   => 'El pago no fue completado. Intenta de nuevo.',
        ]);
    }

    /**
     * Registra el pago en `payments` (exactamente igual que lo haría un admin)
     * y actualiza `client_invoices` para reflejar el nuevo estado.
     *
     * Escribe dentro de una transacción para no dejar datos a medias si algo falla.
     */
    private function registrarPago(int $clientId, object $factura, string $chargeId, float $amount): void
    {
        DB::transaction(function () use ($clientId, $factura, $chargeId, $amount) {
            $hoy = now()->format('d/m/Y');

            // Insertar en `payments` (misma estructura que el resto de pagos)
            $paymentId = DB::table('payments')->insertGetId([
                'payment_method_id' => self::METHOD_ID_OPENPAY,
                'date'              => $hoy,
                'amount'            => $amount,
                'comment'           => 'Pago en línea OpenPay (Sandbox)',
                'receipt'           => $chargeId,    // ID de cargo OpenPay como recibo
                'add_by'            => self::ADD_BY_PORTAL,
                'paymentable_id'    => $clientId,
                'paymentable_type'  => 'App\\Models\\Client',
                'is_first_payment'  => 0,
                'enabled_payment_promise' => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Actualizar la factura: estado + fecha de pago + payment (CSV de IDs)
            $paymentsExistentes = trim($factura->payment ?? '');
            $nuevoPaymentField  = $paymentsExistentes
                ? $paymentsExistentes . ', ' . $paymentId
                : (string) $paymentId;

            DB::table('client_invoices')
                ->where('id', $factura->id)
                ->update([
                    'estado'       => 'Pagado',
                    'payment_date' => $hoy,
                    'payment'      => $nuevoPaymentField,
                    'updated_at'   => now(),
                ]);
        });
    }

    private function estaFacuraPagada(string $estado): bool
    {
        return str_contains(strtolower($estado), 'pagado')
            || str_contains(strtolower($estado), 'paid');
    }
}
