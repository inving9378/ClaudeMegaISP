<?php

namespace App\Modules\Addons\MegaFamilia\Services;

use App\Models\ClientMainInformation;
use App\Modules\Addons\MegaFamilia\Models\ParentalOtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OTP (segundo factor) para la autenticación del padre en MegaFamilia.
 * Política aprobada por Irving (roadmap #496): TTL 5 min, máx 3 intentos,
 * bloqueo 15 min tras agotarlos. Canal: SMS vía Twilio.
 */
class OtpService
{
    private const TTL_MINUTOS = 5;
    private const MAX_INTENTOS = 3;
    private const BLOQUEO_MINUTOS = 15;
    private const REENVIO_COOLDOWN_SEGUNDOS = 60;

    public function __construct(private TwilioSmsChannel $canal)
    {
    }

    public function enviar(ClientMainInformation $cmi, string $proposito = 'login'): array
    {
        $telefono = $this->normalizarTelefono($cmi->phone);
        if (! $telefono) {
            return ['success' => false, 'error' => 'La cuenta no tiene un teléfono válido registrado.'];
        }

        $ultimo = ParentalOtpCode::where('client_main_information_id', $cmi->id)
            ->where('proposito', $proposito)
            ->latest('id')
            ->first();

        // El bloqueo vive en la fila del código: uno nuevo lo esquivaría a los 60 s del reenvío.
        if ($ultimo && $ultimo->bloqueado_hasta && $ultimo->bloqueado_hasta->isFuture()) {
            return ['success' => false, 'error' => 'Demasiados intentos fallidos. Intenta de nuevo en unos minutos.'];
        }

        if ($ultimo && $ultimo->created_at && $ultimo->created_at->gt(now()->subSeconds(self::REENVIO_COOLDOWN_SEGUNDOS))) {
            return ['success' => false, 'error' => 'Espera unos segundos antes de solicitar otro código.'];
        }

        $codigo = (string) random_int(100000, 999999);

        $otp = ParentalOtpCode::create([
            'client_main_information_id' => $cmi->id,
            'destino'                    => $telefono,
            'canal'                      => 'sms',
            'proposito'                  => $proposito,
            'code_hash'                  => hash('sha256', $codigo),
            'intentos'                   => 0,
            'max_intentos'               => self::MAX_INTENTOS,
            'expira_at'                  => now()->addMinutes(self::TTL_MINUTOS),
        ]);

        $enviado = $this->canal->enviar(
            $telefono,
            "Tu código Meganet MegaFamilia es {$codigo}. Vence en " . self::TTL_MINUTOS . ' minutos. No lo compartas.'
        );

        if (! $enviado['success']) {
            // Un código que nadie recibió no debe quedar vigente.
            $otp->delete();

            Log::warning('megafamilia_otp_envio_fallido', [
                'client_main_information_id' => $cmi->id,
                'error'                       => $enviado['error'] ?? null,
            ]);

            return ['success' => false, 'error' => 'No se pudo enviar el código por SMS. Intenta más tarde.'];
        }

        return [
            'success'        => true,
            'expira_en'      => self::TTL_MINUTOS * 60,
            'destino_oculto' => $this->ocultarTelefono($telefono),
        ];
    }

    public function verificar(ClientMainInformation $cmi, string $codigo, string $proposito = 'login'): array
    {
        // Con la fila bloqueada, peticiones simultáneas no pierden intentos fallidos.
        return DB::transaction(function () use ($cmi, $codigo, $proposito) {
            // Solo vale el último emitido: filtrar por no verificados reviviría uno anterior al usar el nuevo.
            $otp = ParentalOtpCode::where('client_main_information_id', $cmi->id)
                ->where('proposito', $proposito)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->verificado_at) {
                return ['success' => false, 'error' => 'No hay un código pendiente. Solicita uno nuevo.'];
            }

            if ($otp->bloqueado_hasta && $otp->bloqueado_hasta->isFuture()) {
                return ['success' => false, 'error' => 'Demasiados intentos fallidos. Intenta de nuevo en unos minutos.'];
            }

            if ($otp->expira_at->isPast()) {
                return ['success' => false, 'error' => 'El código expiró. Solicita uno nuevo.'];
            }

            if (! hash_equals($otp->code_hash, hash('sha256', trim($codigo)))) {
                $otp->intentos++;
                if ($otp->intentos >= $otp->max_intentos) {
                    $otp->bloqueado_hasta = now()->addMinutes(self::BLOQUEO_MINUTOS);
                }
                $otp->save();

                return ['success' => false, 'error' => 'Código incorrecto.'];
            }

            $otp->verificado_at = now();
            $otp->save();

            return ['success' => true];
        });
    }

    private function normalizarTelefono(?string $tel): ?string
    {
        if (! $tel) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $tel);
        if (strlen($digits) < 10) {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+52' . $digits;
        }

        return '+' . $digits;
    }

    private function ocultarTelefono(string $tel): string
    {
        return str_repeat('*', max(strlen($tel) - 4, 0)) . substr($tel, -4);
    }
}
