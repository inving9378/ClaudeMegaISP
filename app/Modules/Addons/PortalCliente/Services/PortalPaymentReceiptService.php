<?php

namespace App\Modules\Addons\PortalCliente\Services;

use App\Modules\Addons\PortalCliente\Mail\PortalPaymentReceiptMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envía el recibo de pago al cliente tras un cargo OpenPay exitoso en el portal.
 *
 * Aislado del flujo de dinero: se llama SIEMPRE después de que el pago ya quedó
 * registrado (fuera de la transacción). Un fallo aquí (SMTP caído, email vacío,
 * etc.) se loguea y se absorbe — NUNCA debe tumbar ni revertir el pago.
 */
class PortalPaymentReceiptService
{
    public static function enviar(int $clientId, int $invoiceId, string $invoiceNumber, float $amount, string $chargeId): void
    {
        try {
            $cmi = DB::table('client_main_information')
                ->where('client_id', $clientId)
                ->first(['name', 'father_last_name', 'email']);

            $email = trim((string) ($cmi->email ?? ''));
            if ($email === '') {
                Log::channel('single')->info('[Portal Recibo] Cliente sin email, no se envía recibo', [
                    'client_id'  => $clientId,
                    'invoice_id' => $invoiceId,
                ]);
                return;
            }

            $nombre = trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? ''));

            $data = [
                'client_name'    => $nombre,
                'invoice_number' => $invoiceNumber,
                'amount'         => $amount,
                'date'           => now()->format('d/m/Y H:i'),
                'method'         => 'Tarjeta OpenPay',
                'charge_id'      => $chargeId,
            ];

            Mail::to($email)->queue(new PortalPaymentReceiptMail($data));
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[Portal Recibo] No se pudo encolar el recibo de pago', [
                'client_id'  => $clientId,
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
