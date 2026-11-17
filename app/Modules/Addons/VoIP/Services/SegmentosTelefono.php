<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\RangoNumeracion;

/**
 * Dos segmentos de numeración que no se mezclan (pedido de Irving, 17-nov-2026):
 *
 *   1000–1999  aparatos físicos (teléfono IP, softphone): un número por aparato.
 *   2000–2999  teléfonos web (el teléfono del navegador): un número por PERSONA.
 *
 * Antes cada extensión física 10xx tenía una «gemela» web10xx ligada: marcar 1003
 * timbraba el aparato y, según el servidor, también el navegador de su dueño. Los
 * teléfonos de mostrador quedaban ligados a la línea web de una persona, y un teléfono
 * físico no podía marcarle a una línea web (no se marcan letras). Ahora cada línea
 * tiene su propio número de 4 dígitos y se marca igual desde cualquier teléfono.
 *
 * Las áreas son las mismas en los dos segmentos: 1100 atención ↔ 2100 atención web.
 */
final class SegmentosTelefono
{
    public const WEB_DESDE = '2000';
    public const WEB_HASTA = '2999';

    /** Sufijo del código de un rango web: «atencion» → «atencion_web». */
    public const SUFIJO_WEB = '_web';

    /** Lo que lleva todo teléfono web (el navegador solo habla WebRTC por WSS). */
    public const DATOS_WEB = [
        'tipo_dispositivo' => 'softphone',
        'es_webrtc'        => true,
        // ulaw primero: esta central no transcodifica Opus (ver AsteriskProvisioningService).
        'codecs'           => 'ulaw,alaw,opus',
        'transporte'       => 'transport-wss',
    ];

    public static function esNumeroWeb(?string $numero): bool
    {
        $numero = trim((string) $numero);

        return strlen($numero) === 4 && ctype_digit($numero)
            && strcmp($numero, self::WEB_DESDE) >= 0 && strcmp($numero, self::WEB_HASTA) <= 0;
    }

    /** Número de una gemela vieja: «web1011» → «1011»; null si no es una. */
    public static function baseDeGemela(?string $numero): ?string
    {
        return preg_match('/^web(\d+)$/i', trim((string) $numero), $m) ? $m[1] : null;
    }

    /** El número web que conserva la terminación del físico: 1011 → 2011; null si no es 1xxx. */
    public static function webEspejo(string $base): ?string
    {
        return preg_match('/^1\d{3}$/', $base) ? '2' . substr($base, 1) : null;
    }

    /** Rango web de la misma área que un rango físico («atencion» → «atencion_web»). */
    public static function rangoWebDe(?RangoNumeracion $fisico): ?RangoNumeracion
    {
        if (! $fisico || str_ends_with($fisico->codigo, self::SUFIJO_WEB)) {
            return $fisico;
        }

        return RangoNumeracion::porCodigo($fisico->codigo . self::SUFIJO_WEB)->first();
    }

    /** El rango (activo) donde cae un número, físico o web. */
    public static function rangoDe(string $numero): ?RangoNumeracion
    {
        return RangoNumeracion::activos()->get()->first(fn (RangoNumeracion $r) => $r->contiene(trim($numero)));
    }

    /**
     * Primer número libre de un rango web. Sin rango, cualquiera libre del segmento web.
     *
     * @param  list<string>  $apartados  números que ya se van a usar en esta misma pasada
     */
    public static function primerLibre(?RangoNumeracion $rango, array $apartados = []): ?string
    {
        $desde = (int) ($rango->desde ?? self::WEB_DESDE);
        $hasta = (int) ($rango->hasta ?? self::WEB_HASTA);
        $ocupados = Extension::whereBetween('numero', [(string) $desde, (string) $hasta])->pluck('numero')
            ->merge($apartados)->flip();

        for ($n = $desde; $n <= $hasta; $n++) {
            // El primero de cada área se deja libre, igual que en los físicos (1100, 1200…).
            if ($n === $desde && $rango) {
                continue;
            }
            if (! isset($ocupados[(string) $n])) {
                return (string) $n;
            }
        }

        return null;
    }
}
