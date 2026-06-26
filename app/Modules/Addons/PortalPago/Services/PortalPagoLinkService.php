<?php

namespace App\Modules\Addons\PortalPago\Services;

use App\Models\ClientInvoice;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Alta y resolución de ligas de pago.
 *
 * Anti-IDOR: el token se genera con Str::uuid() (36 chars, no enumerable). La
 * resolución para pago aplica fail-closed: cualquier liga inexistente, expirada
 * o ya conciliada se trata igual (el controlador responde 404 neutro), de modo
 * que un atacante no pueda distinguir "no existe" de "existe pero expiró".
 */
class PortalPagoLinkService
{
    /**
     * Crea una liga de pago para una factura legacy (client_invoices).
     */
    public function generate(ClientInvoice $invoice, int $accountId, ?float $monto = null, ?int $ttlDays = null): PortalPagoPaymentLink
    {
        $ttlDays = $ttlDays ?? (int) config('pagos.link_ttl_days', 7);
        $monto   = $monto ?? (float) $invoice->total;

        return PortalPagoPaymentLink::create([
            'token'            => (string) Str::uuid(),
            'document_id'      => $invoice->id,
            'client_id'        => $invoice->client_id,
            'account_id'       => $accountId,
            'monto_esperado'   => number_format($monto, 2, '.', ''),
            'referencia_unica' => $this->uniqueReference(),
            'estado'           => PortalPagoPaymentLink::ESTADO_PENDIENTE,
            'expira_at'        => Carbon::now()->addDays($ttlDays),
        ]);
    }

    /**
     * Resuelve una liga SOLO si es pagable: existe, no expiró y su estado admite
     * pago. Devuelve null en cualquier otro caso (→ 404 neutro). NO distingue
     * causas para evitar enumeración.
     */
    public function findByTokenForPayment(string $token): ?PortalPagoPaymentLink
    {
        $link = $this->findByToken($token);
        if ($link === null) {
            return null;
        }

        if ($link->isExpired()) {
            return null;
        }

        $pagable = in_array($link->estado, [
            PortalPagoPaymentLink::ESTADO_PENDIENTE,
            PortalPagoPaymentLink::ESTADO_REPORTADO,   // puede reintentar reporte si quedó pendiente/discrepancia
        ], true);

        return $pagable ? $link : null;
    }

    /**
     * Resolución cruda por token (existencia). Usada por la pantalla de estado,
     * donde sí es válido mostrar conciliado/expirado.
     */
    public function findByToken(string $token): ?PortalPagoPaymentLink
    {
        // Validación de forma antes de pegarle a la BD: un token válido es un UUID.
        if (! Str::isUuid($token)) {
            return null;
        }

        return PortalPagoPaymentLink::where('token', $token)->first();
    }

    private function uniqueReference(): string
    {
        do {
            $ref = 'PP' . strtoupper(Str::random(10));
        } while (PortalPagoPaymentLink::where('referencia_unica', $ref)->exists());

        return $ref;
    }
}
