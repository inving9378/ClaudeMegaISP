<?php

namespace App\Listeners\Correo;

use App\Listeners\Correo\InterceptarCorreoDev;
use App\Models\BillingDocumentoEvidencia as Evidencia;
use App\Services\Finance\Billing\EvidenciaDocumentoService;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * FAC-05 (item #510): cuando el transporte entrega el correo al servidor (MessageSent), las
 * evidencias ligadas por la cabecera X-MegaISP-Evidencia pasan a «aceptado» con su Message-ID.
 * Si la interceptación de prueba estaba activa, se anota: no llegó al destinatario original.
 */
class MarcarEvidenciaAceptada
{
    public function handle(MessageSent $event): void
    {
        try {
            $cabeceras = $event->sent->getOriginalMessage()->getHeaders();
            $ids = EvidenciaDocumentoService::idsDeCabecera($cabeceras->get(EvidenciaDocumentoService::CABECERA)?->getBodyAsString());
            if (! $ids) {
                return;
            }

            Evidencia::whereIn('id', $ids)->update([
                'estado'       => Evidencia::ESTADO_ACEPTADO,
                'aceptado_at'  => now(),
                'message_id'   => $event->sent->getMessageId(),
                'interceptado' => $cabeceras->has(InterceptarCorreoDev::CABECERA),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Evidencia] No se pudo marcar como aceptada: ' . $e->getMessage());
        }
    }
}
