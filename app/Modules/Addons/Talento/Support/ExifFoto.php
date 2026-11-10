<?php

namespace App\Modules\Addons\Talento\Support;

use Carbon\Carbon;

/**
 * Ubicación y hora del EXIF de una foto (item #1311), leídas ANTES de reducirla (al volver a
 * codificar la imagen el EXIF se pierde). La hora de la cámara no trae zona: si la foto tiene
 * OffsetTimeOriginal se usa; si no, se toma en la zona de la app.
 */
final class ExifFoto
{
    /** @return array{lat: ?float, lng: ?float, tomada_at: ?Carbon} */
    public static function leer(string $ruta): array
    {
        $exif = function_exists('exif_read_data') ? (@exif_read_data($ruta, 'ANY_TAG', true) ?: []) : [];
        $gps = $exif['GPS'] ?? [];

        $lat = self::coordenada($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? 'N');
        $lng = self::coordenada($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? 'E');
        if ($lat === null || $lng === null || ($lat == 0.0 && $lng == 0.0) || abs($lat) > 90 || abs($lng) > 180) {
            $lat = $lng = null;
        }

        return ['lat' => $lat, 'lng' => $lng, 'tomada_at' => self::hora($exif)];
    }

    private static function coordenada($partes, $ref): ?float
    {
        if (! is_array($partes) || count($partes) < 3) {
            return null;
        }
        [$g, $m, $s] = array_map([self::class, 'racional'], array_slice(array_values($partes), 0, 3));
        if ($g === null || $m === null || $s === null) {
            return null;
        }
        $valor = $g + $m / 60 + $s / 3600;

        return round(in_array(strtoupper(trim((string) $ref)), ['S', 'W'], true) ? -$valor : $valor, 7);
    }

    private static function racional($v): ?float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        if (is_string($v) && preg_match('#^(-?\d+(?:\.\d+)?)/(\d+(?:\.\d+)?)$#', trim($v), $m)) {
            return (float) $m[2] == 0.0 ? null : (float) $m[1] / (float) $m[2];
        }

        return null;
    }

    private static function hora(array $exif): ?Carbon
    {
        $valor = $exif['EXIF']['DateTimeOriginal'] ?? $exif['EXIF']['DateTimeDigitized'] ?? $exif['IFD0']['DateTime'] ?? null;
        if (! is_string($valor) || ! preg_match('/^\d{4}:\d{2}:\d{2} \d{2}:\d{2}:\d{2}/', $valor)) {
            return null;
        }
        $zona = config('app.timezone');
        // PHP no conoce estas etiquetas EXIF 2.31: las entrega como «UndefinedTag:0x9011» / «0x9010».
        $offset = $exif['EXIF']['OffsetTimeOriginal'] ?? $exif['EXIF']['UndefinedTag:0x9011']
            ?? $exif['EXIF']['OffsetTime'] ?? $exif['EXIF']['UndefinedTag:0x9010'] ?? null;
        if (is_string($offset) && preg_match('/^[+-]\d{2}:\d{2}$/', trim($offset))) {
            $zona = trim($offset);
        }
        try {
            return Carbon::createFromFormat('Y:m:d H:i:s', substr($valor, 0, 19), $zona)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
