<?php

namespace Tests\Unit\Talento;

use ZipArchive;

/**
 * Item #1311 — rutas y fotos SINTÉTICAS para las pruebas de Recorridos: GPX/KML/KMZ generados y
 * JPEG con EXIF armado a mano (ubicación y hora). Las coordenadas son una línea recta inventada,
 * sin relación con clientes ni con la red.
 */
final class RecorridoFixtures
{
    public const LAT0 = 19.4000000;
    public const LNG0 = -99.1500000;

    /** $n puntos hacia el este, uno cada $pasoS segundos y ~$pasoM metros, desde $t0 (unix). */
    public static function linea(int $n, int $t0, int $pasoS = 5, float $pasoM = 5.0): array
    {
        $dLng = $pasoM / (6371000 * M_PI / 180 * cos(deg2rad(self::LAT0)));
        $puntos = [];
        for ($i = 0; $i < $n; $i++) {
            $puntos[] = [self::LAT0, round(self::LNG0 + $i * $dLng, 7), $t0 + $i * $pasoS];
        }

        return $puntos;
    }

    public static function gpx(array $puntos): string
    {
        $pts = '';
        foreach ($puntos as [$lat, $lng, $t]) {
            $hora = $t !== null ? '<time>' . gmdate('Y-m-d\TH:i:s\Z', $t) . '</time>' : '';
            $pts .= "<trkpt lat=\"$lat\" lon=\"$lng\"><ele>2240</ele>$hora</trkpt>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<gpx version="1.1" creator="prueba" xmlns="http://www.topografix.com/GPX/1/1">'
            . '<metadata><time>2000-01-01T00:00:00Z</time></metadata>'
            . "<trk><name>Prueba</name><trkseg>\n$pts</trkseg></trk></gpx>";
    }

    public static function kmlTrack(array $puntos): string
    {
        $whens = $coords = '';
        foreach ($puntos as [$lat, $lng, $t]) {
            $whens .= '<when>' . gmdate('Y-m-d\TH:i:s\Z', $t) . "</when>\n";
            $coords .= "<gx:coord>$lng $lat 2240</gx:coord>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2"><Document>'
            . '<Placemark><name>Inicio</name><Point><coordinates>-1,1,0</coordinates></Point></Placemark>'
            . "<Placemark><gx:Track>\n$whens$coords</gx:Track></Placemark></Document></kml>";
    }

    public static function kmlLinea(array $puntos): string
    {
        $coords = implode(' ', array_map(fn ($p) => "{$p[1]},{$p[0]},0", $puntos));

        return '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>'
            . '<Placemark><Point><coordinates>-1,1,0</coordinates></Point></Placemark>'
            . "<Placemark><LineString><coordinates>$coords</coordinates></LineString></Placemark></Document></kml>";
    }

    public static function kmz(string $kml): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kmzp');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('doc.kml', $kml);
        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);

        return $bin;
    }

    /**
     * JPEG de $ancho×$alto con EXIF opcional: GPS ($lat, $lng) y hora de la cámara
     * ($fecha 'Y:m:d H:i:s', sin zona, y $offset '+HH:MM' opcional).
     */
    public static function jpeg(int $ancho = 2400, int $alto = 1800, ?float $lat = null, ?float $lng = null, ?string $fecha = null, ?string $offset = null): string
    {
        $img = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($img, 0, 0, $ancho, $alto, imagecolorallocate($img, 90, 140, 200));
        for ($i = 0; $i < 40; $i++) {
            imageline($img, random_int(0, $ancho), random_int(0, $alto), random_int(0, $ancho), random_int(0, $alto), imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagejpeg($img, null, 95);
        $jpg = ob_get_clean();
        imagedestroy($img);

        $exif = [];
        if ($fecha) {
            $exif[] = [0x9003, 2, $fecha . "\0"];
        }
        if ($offset) {
            $exif[] = [0x9011, 2, $offset . "\0"];
        }
        $gps = [];
        if ($lat !== null && $lng !== null) {
            $gps = [
                [0x0001, 2, ($lat >= 0 ? 'N' : 'S') . "\0"],
                [0x0002, 5, self::racionales(abs($lat))],
                [0x0003, 2, ($lng >= 0 ? 'E' : 'W') . "\0"],
                [0x0004, 5, self::racionales(abs($lng))],
            ];
        }
        if (! $exif && ! $gps) {
            return $jpg;
        }

        $n0 = ($exif ? 1 : 0) + ($gps ? 1 : 0);
        $offExif = 8 + 2 + 12 * $n0 + 4;
        $bloqueExif = $exif ? self::ifd($exif, $offExif) : '';
        $offGps = $offExif + strlen($bloqueExif);
        $bloqueGps = $gps ? self::ifd($gps, $offGps) : '';

        $ifd0 = [];
        if ($exif) {
            $ifd0[] = [0x8769, 4, pack('V', $offExif)];
        }
        if ($gps) {
            $ifd0[] = [0x8825, 4, pack('V', $offGps)];
        }
        $tiff = 'II' . pack('v', 42) . pack('V', 8) . self::ifd($ifd0, 8) . $bloqueExif . $bloqueGps;
        $app1 = "Exif\0\0" . $tiff;

        return substr($jpg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpg, 2);
    }

    private static function racionales(float $grados): string
    {
        $g = (int) floor($grados);
        $m = (int) floor(($grados - $g) * 60);
        $s = (($grados - $g) * 60 - $m) * 60;

        return pack('VV', $g, 1) . pack('VV', $m, 1) . pack('VV', (int) round($s * 10000), 10000);
    }

    /** IFD con sus datos a continuación; $entradas = [[tag, tipo, bytes], …] ya ordenadas por tag. */
    private static function ifd(array $entradas, int $offset): string
    {
        $datosOff = $offset + 2 + 12 * count($entradas) + 4;
        $tabla = pack('v', count($entradas));
        $datos = '';
        foreach ($entradas as [$tag, $tipo, $bytes]) {
            $cuenta = match ($tipo) { 2 => strlen($bytes), 4 => 1, 5 => intdiv(strlen($bytes), 8) };
            if (strlen($bytes) <= 4) {
                $valor = str_pad($bytes, 4, "\0");
            } else {
                $valor = pack('V', $datosOff + strlen($datos));
                $datos .= $bytes . (strlen($bytes) % 2 ? "\0" : '');
            }
            $tabla .= pack('vvV', $tag, $tipo, $cuenta) . $valor;
        }

        return $tabla . pack('V', 0) . $datos;
    }
}
