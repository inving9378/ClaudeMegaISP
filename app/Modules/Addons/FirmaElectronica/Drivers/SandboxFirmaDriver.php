<?php

namespace App\Modules\Addons\FirmaElectronica\Drivers;

use App\Modules\Addons\FirmaElectronica\Contracts\FirmaProviderInterface;
use App\Modules\Addons\FirmaElectronica\Models\FirmaSolicitud;
use Illuminate\Support\Str;

/** Proveedor simulado: no envía nada a terceros. */
class SandboxFirmaDriver implements FirmaProviderInterface
{
    public function nombre(): string
    {
        return 'sandbox';
    }

    public function crearSolicitud(FirmaSolicitud $solicitud): string
    {
        return 'sbx_' . Str::lower(Str::random(16));
    }

    public function consultarEstado(string $idExterno): string
    {
        return FirmaSolicitud::where('id_externo', $idExterno)->value('estado') ?? FirmaSolicitud::ERROR;
    }

    public function cancelar(string $idExterno): bool
    {
        return true;
    }

    public function descargarResultado(string $idExterno): ?string
    {
        return null;
    }

    public function verificarWebhook(string $cuerpo, ?string $firma): bool
    {
        $secreto = (string) config('firma_electronica.webhook_secreto', '');
        if ($secreto === '' || $firma === null || $firma === '') {
            return false; // falla cerrado
        }
        return hash_equals(hash_hmac('sha256', $cuerpo, $secreto), $firma);
    }

    /** Firma de prueba para simular un webhook válido. */
    public static function firmar(string $cuerpo, string $secreto): string
    {
        return hash_hmac('sha256', $cuerpo, $secreto);
    }
}
