<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalCliente\Services\OpenpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook de OpenPay para robustez en producción.
 *
 * OpenPay llama a esta ruta cuando un cargo cambia de estado.
 * Para tarjeta síncrona, la respuesta del cargo ya es la fuente de verdad
 * (cobrar() en PortalPagoController). Este webhook actúa como capa de conciliación:
 * si por algún motivo el cobro fue completado en OpenPay pero no se registró
 * (crash del servidor, timeout), el webhook lo cierra correctamente.
 *
 * NOTA: Esta ruta está FUERA del guard `cliente` (es llamada por OpenPay, no por el cliente).
 * La autenticación es vía Basic Auth donde la contraseña es la private_key de OpenPay.
 *
 * ACTIVACIÓN: Configurar en el dashboard de OpenPay una vez que el subdominio esté
 * publicado con SSL. URL: https://portal.meganet.mx/portal/openpay/webhook
 * (ver Roadmap en CLAUDE.md: "configurar webhook OpenPay al publicar subdominio/SSL")
 */
class OpenpayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // ── Autenticación Basic Auth (contraseña = private_key de OpenPay) ──
        if (! $this->validarBasicAuth($request)) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="OpenPay Webhook"',
            ]);
        }

        $payload = $request->all();
        $tipo    = $payload['type'] ?? '';
        $charge  = $payload['transaction'] ?? null;

        Log::channel('single')->info('[OpenPay Webhook] Evento recibido', [
            'type'      => $tipo,
            'charge_id' => $charge['id'] ?? null,
            'status'    => $charge['status'] ?? null,
            'order_id'  => $charge['order_id'] ?? null,
        ]);

        // Solo procesar cargos completados
        if ($tipo !== 'charge.succeeded' && $tipo !== 'transaction.success') {
            return response()->json(['received' => true]);
        }

        if (! $charge || empty($charge['id'])) {
            return response()->json(['received' => true]);
        }

        $this->conciliarCargo($charge);

        return response()->json(['received' => true]);
    }

    /**
     * Concilia un cargo que llegó como completado vía webhook.
     *
     * Busca el intento por order_id (o charge_id) y, si no fue ya registrado,
     * crea el pago en la estructura existente (idempotente: no duplica).
     */
    private function conciliarCargo(array $charge): void
    {
        $chargeId = $charge['id'];
        $orderId  = $charge['order_id'] ?? null;

        // Buscar intento por order_id (primera opción) o por openpay_charge_id (fallback)
        $intento = null;
        if ($orderId) {
            $intento = DB::table('portal_payment_attempts')
                ->where('order_id', $orderId)
                ->first();
        }
        if (! $intento) {
            $intento = DB::table('portal_payment_attempts')
                ->where('openpay_charge_id', $chargeId)
                ->first();
        }

        if (! $intento) {
            Log::warning('[OpenPay Webhook] Cargo sin intento registrado', ['charge_id' => $chargeId, 'order_id' => $orderId]);
            return;
        }

        // Si ya está completed → idempotente, no hacer nada
        if ($intento->status === 'completed') {
            return;
        }

        // Obtener la factura (verificar que aún no esté pagada)
        $factura = DB::table('client_invoices')
            ->where('id', $intento->invoice_id)
            ->where('client_id', $intento->client_id)
            ->first(['id', 'estado', 'payment', 'number', 'total']);

        if (! $factura) {
            Log::warning('[OpenPay Webhook] Factura no encontrada para intento', ['attempt_id' => $intento->id]);
            return;
        }

        $esPagada = str_contains(strtolower($factura->estado), 'pagado')
                 || str_contains(strtolower($factura->estado), 'paid');

        if ($esPagada) {
            // Ya pagada (quizás por el flujo síncrono) — solo actualizar el intento
            DB::table('portal_payment_attempts')
                ->where('id', $intento->id)
                ->update([
                    'status'            => 'completed',
                    'openpay_charge_id' => $chargeId,
                    'updated_at'        => now(),
                ]);
            return;
        }

        // Registrar pago y actualizar factura (idempotente vía transacción)
        DB::transaction(function () use ($intento, $factura, $chargeId, $charge) {
            $hoy = now()->format('d/m/Y');

            $paymentId = DB::table('payments')->insertGetId([
                'payment_method_id' => PortalPagoController::METHOD_ID_OPENPAY ?? 9,
                'date'              => $hoy,
                'amount'            => $intento->amount,
                'comment'           => 'Pago en línea OpenPay (Webhook conciliación)',
                'receipt'           => $chargeId,
                'add_by'            => 0,
                'paymentable_id'    => $intento->client_id,
                'paymentable_type'  => 'App\\Models\\Client',
                'is_first_payment'  => 0,
                'enabled_payment_promise' => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

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

            DB::table('portal_payment_attempts')
                ->where('id', $intento->id)
                ->update([
                    'status'            => 'completed',
                    'openpay_charge_id' => $chargeId,
                    'updated_at'        => now(),
                ]);
        });

        Log::info('[OpenPay Webhook] Pago conciliado correctamente', [
            'charge_id'  => $chargeId,
            'invoice_id' => $factura->id,
            'amount'     => $intento->amount,
        ]);
    }

    /**
     * Valida Basic Auth. La contraseña esperada es la private_key de OpenPay.
     * OpenPay envía el webhook con Basic Auth donde password = private_key del comercio.
     */
    private function validarBasicAuth(Request $request): bool
    {
        $usuario = $request->getUser();
        $pass    = $request->getPassword();

        if (! $pass) {
            return false;
        }

        // La contraseña debe ser la private_key (hash_equals para evitar timing attacks)
        return hash_equals(config('openpay.private_key') ?? '', $pass);
    }
}
