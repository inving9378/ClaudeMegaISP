<?php

namespace App\Modules\Addons\WhatsAppAgent\Listeners;

use App\Modules\Addons\Embajadores\Events\ReferralShareRequested;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;
use Illuminate\Support\Facades\Log;

/**
 * Consumidor del evento de dominio de Embajadores → gateway ÚNICO de WhatsApp
 * (item #253: Embajadores ya no llama a Marketing\EvolutionApiService directo).
 *
 * Síncrono a propósito (NO ShouldQueue): el emisor (shareMasivo) necesita el
 * resumen sent/failed en la misma respuesta HTTP. El envío REAL a Evolution lo
 * hace el gateway de forma asíncrona (sendAndLog encola SendWhatsAppMessageJob
 * + respeta el freno maestro `whatsapp.sender_enabled`) — aquí solo se encola,
 * no se confirma entrega (cambio de sync a async aceptado por Irving, item #253).
 */
class SendReferralShareListener
{
    public function __construct(private WhatsAppGateway $gateway)
    {
    }

    /** @return array{sent:int, failed:int, total:int} */
    public function handle(ReferralShareRequested $event): array
    {
        $sent   = 0;
        $failed = 0;

        foreach ($event->contacts as $contact) {
            try {
                $numero = $this->normalizeNumber((string) $contact['phone']);
                $this->gateway->sendText(null, $numero, (string) $contact['body'], [
                    'source'        => 'embajadores.share_masivo',
                    'embajador_id'  => $event->embajadorId,
                    'referral_code' => $event->referralCode,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('SendReferralShareListener: fallo en contacto', [
                    'phone' => $contact['phone'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($event->contacts)];
    }

    /** Mismo formato que espera whatsapp_conversations.contact_number (MX 10 dígitos → prefijo 521). */
    private function normalizeNumber(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) === 10) {
            $digits = '521' . $digits;
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = '521' . substr($digits, 2);
        }

        return $digits;
    }
}
