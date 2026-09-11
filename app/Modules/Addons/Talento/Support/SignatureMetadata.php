<?php

namespace App\Modules\Addons\Talento\Support;

/**
 * Item #9990813 — extrae parsearDispositivo()/sanearGeolocalizacion() de
 * TalentoEmployeeDocumentController::sign() (que se deja intacto, ya aprobado — item #9990805)
 * para que el endpoint self-scoped del Portal de Colaborador (PortalTecnicoController::firmarDocumento)
 * calcule la MISMA metadata legal sin duplicar la lógica. Copia fiel del comportamiento original.
 */
class SignatureMetadata
{
    /** Etiqueta legible de dispositivo (tipo · navegador · SO) parseada del User-Agent crudo. */
    public static function dispositivo(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $so = 'SO desconocido';
        if (preg_match('/Windows/i', $userAgent)) {
            $so = 'Windows';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $so = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod|iOS/i', $userAgent)) {
            $so = 'iOS';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $so = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $so = 'Linux';
        }

        $navegador = 'navegador desconocido';
        if (preg_match('/Edg\//i', $userAgent)) {
            $navegador = 'Edge';
        } elseif (preg_match('/OPR\/|Opera/i', $userAgent)) {
            $navegador = 'Opera';
        } elseif (preg_match('/Chrome\//i', $userAgent) && !preg_match('/Chromium/i', $userAgent)) {
            $navegador = 'Chrome';
        } elseif (preg_match('/Firefox\//i', $userAgent)) {
            $navegador = 'Firefox';
        } elseif (preg_match('/Safari\//i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
            $navegador = 'Safari';
        }

        $tipo = 'Escritorio';
        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            $tipo = 'Tablet';
        } elseif (preg_match('/Mobile|Android|iPhone/i', $userAgent)) {
            $tipo = 'Móvil';
        }

        return "{$tipo} · {$navegador} · {$so}";
    }

    /**
     * Geolocalización opt-in: solo se persiste si trae lat/lng numéricos en rango válido.
     * Acepta tanto array nativo (JSON body) como string JSON (FormData con archivo adjunto).
     */
    public static function geolocalizacion($valor): ?array
    {
        if (is_string($valor)) {
            $valor = json_decode($valor, true);
        }

        if (!is_array($valor) || !isset($valor['lat'], $valor['lng'])) {
            return null;
        }

        $lat = (float) $valor['lat'];
        $lng = (float) $valor['lng'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => isset($valor['accuracy']) ? (float) $valor['accuracy'] : null,
        ];
    }
}
