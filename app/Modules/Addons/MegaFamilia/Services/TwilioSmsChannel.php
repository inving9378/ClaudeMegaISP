<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de SMS vía Twilio REST API (sin SDK — Http::withBasicAuth, evita sumar
 * una dependencia de Composer para un solo endpoint). Requiere en `.env`:
 * TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER. Sin esas llaves, falla
 * limpio (log + success:false) — mismo patrón que otras integraciones del
 * proyecto pendientes de configuración (OpenPay, Evolution, etc.).
 */
class TwilioSmsChannel
{
    public function enviar(string $telefonoE164, string $mensaje): array
    {
        $sid   = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from  = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            Log::warning('megafamilia_twilio_no_configurado', [
                'motivo' => 'Faltan TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM_NUMBER en .env',
            ]);

            return ['success' => false, 'error' => 'Twilio no está configurado.'];
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To'   => $telefonoE164,
                    'From' => $from,
                    'Body' => $mensaje,
                ]);

            if ($response->successful()) {
                return ['success' => true];
            }

            Log::warning('megafamilia_twilio_envio_fallido', ['status' => $response->status()]);

            return ['success' => false, 'error' => 'Twilio rechazó el envío.'];
        } catch (\Throwable $e) {
            Log::error('megafamilia_twilio_excepcion', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Error de red al contactar Twilio.'];
        }
    }
}
