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
 *
 * El aparato y el teléfono web son dos lugares distintos (Irving, 20-nov-2026): el número
 * web es uno disponible y nunca «se parece» al de un aparato. Si existe la 1003, no hay
 * 2003: así nadie marca uno creyendo que timbra el otro.
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

    /**
     * El número del otro segmento con la misma terminación (1003 ↔ 2003): el que «se le
     * parece». Null si no es un número de 4 dígitos de 1000–2999.
     */
    public static function parecido(string $numero): ?string
    {
        $numero = trim($numero);
        if (! preg_match('/^[12]\d{3}$/', $numero)) {
            return null;
        }

        return ($numero[0] === '1' ? '2' : '1') . substr($numero, 1);
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
     * Primer número libre de un rango web que no se parezca a un aparato (si existe la 1003,
     * se salta la 2003). Sin rango, cualquiera del segmento web.
     *
     * @param  list<string>  $apartados  números que ya se van a usar en esta misma pasada
     */
    public static function primerLibre(?RangoNumeracion $rango, array $apartados = []): ?string
    {
        $desde = (int) ($rango->desde ?? self::WEB_DESDE);
        $hasta = (int) ($rango->hasta ?? self::WEB_HASTA);
        $ocupados = Extension::whereBetween('numero', [(string) $desde, (string) $hasta])->pluck('numero')
            ->merge($apartados)->flip();
        $aparatos = Extension::whereBetween('numero', ['1000', '1999'])->pluck('numero')->flip();

        for ($n = $desde; $n <= $hasta; $n++) {
            // El primero de cada área se deja libre, igual que en los físicos (1100, 1200…).
            if ($n === $desde && $rango) {
                continue;
            }
            $numero = (string) $n;
            if (! isset($ocupados[$numero]) && ! isset($aparatos[(string) self::parecido($numero)])) {
                return $numero;
            }
        }

        return null;
    }

    /** Primer libre en el área web; si el área está llena, en cualquier parte del segmento web. */
    public static function disponible(?RangoNumeracion $rango, array $apartados = []): ?string
    {
        return self::primerLibre(self::rangoWebDe($rango), $apartados)
            ?? ($rango ? self::primerLibre(null, $apartados) : null);
    }
}
