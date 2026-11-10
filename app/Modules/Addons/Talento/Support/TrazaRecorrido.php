<?php

namespace App\Modules\Addons\Talento\Support;

use Carbon\Carbon;
use InvalidArgumentException;
use XMLReader;
use ZipArchive;

/**
 * Lectura y cálculo de la ruta de un recorrido (item #1311).
 *
 * Lee GPX (trkpt/rtept), KML (gx:Track con horas, o LineString sin horas) y KMZ (el KML dentro del
 * zip) por streaming con XMLReader, sin resolver entidades externas. Un punto es [lat, lng, t] con
 * t = segundos unix o null. Las horas sin zona se toman en la zona de la app (el GPX de Geo Tracker
 * trae UTC con «Z»; el de Flotas puede traer la hora local sin zona).
 *
 * Funciones puras: no tocan BD ni disco. Las usa Services\RecorridoImportService.
 */
final class TrazaRecorrido
{
    public const MAX_PUNTOS = 200000;
    public const MAX_KML_EN_KMZ = 60 * 1024 * 1024;
    public const MAX_GEOMETRIA = 4000;
    public const TAMANO_MUESTRA = 600;

    /** gpx|kml|kmz según el contenido (y, si no se distingue, la extensión); null si no es ninguno. */
    public static function formatoDe(string $contenido, ?string $nombre = null): ?string
    {
        if (str_starts_with($contenido, "PK\x03\x04")) {
            return 'kmz';
        }
        $inicio = strtolower(substr($contenido, 0, 4096));
        if (str_contains($inicio, '<gpx')) {
            return 'gpx';
        }
        if (str_contains($inicio, '<kml')) {
            return 'kml';
        }
        $ext = strtolower(pathinfo((string) $nombre, PATHINFO_EXTENSION));

        return in_array($ext, ['gpx', 'kml'], true) && str_contains($inicio, '<') ? $ext : null;
    }

    /**
     * @return array<int, array{0: float, 1: float, 2: ?int}>
     * @throws InvalidArgumentException si el archivo no se puede leer o trae demasiados puntos.
     */
    public static function leer(string $contenido, string $formato): array
    {
        if ($formato === 'kmz') {
            $contenido = self::kmlDeKmz($contenido);
            $formato = 'kml';
        }

        return match ($formato) {
            'gpx'   => self::leerGpx($contenido),
            'kml'   => self::leerKml($contenido),
            default => throw new InvalidArgumentException('Formato de ruta no soportado.'),
        };
    }

    private static function lector(string $contenido): XMLReader
    {
        $r = new XMLReader();
        if (! $r->XML($contenido, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidArgumentException('El archivo de ruta no es un XML válido.');
        }

        return $r;
    }

    /** Recorre el documento; un error de XML a media lectura se vuelve InvalidArgumentException. */
    private static function recorrer(string $contenido, callable $alNodo): void
    {
        $previo = libxml_use_internal_errors(true);
        try {
            $r = self::lector($contenido);
            while (@$r->read()) {
                $alNodo($r);
            }
            $errores = libxml_get_errors();
            $r->close();
            if ($errores) {
                throw new InvalidArgumentException('El archivo de ruta está dañado o incompleto.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previo);
        }
    }

    private static function leerGpx(string $contenido): array
    {
        $track = [];
        $ruta = [];
        $actual = null;     // ['lista' => 'track'|'ruta', 'lat' => , 'lng' => , 't' => ]
        $enTime = false;

        self::recorrer($contenido, function (XMLReader $r) use (&$track, &$ruta, &$actual, &$enTime) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->localName === 'trkpt' || $r->localName === 'rtept') {
                    $actual = ['lista' => $r->localName === 'trkpt' ? 'track' : 'ruta',
                        'lat' => $r->getAttribute('lat'), 'lng' => $r->getAttribute('lon'), 't' => null];
                    if ($r->isEmptyElement) {
                        $actual['lista'] === 'track'
                            ? self::agregar($track, $actual['lat'], $actual['lng'], null)
                            : self::agregar($ruta, $actual['lat'], $actual['lng'], null);
                        $actual = null;
                    }
                } elseif ($actual && $r->localName === 'time') {
                    $enTime = true;
                }
            } elseif (($r->nodeType === XMLReader::TEXT || $r->nodeType === XMLReader::CDATA) && $enTime && $actual) {
                $actual['t'] = self::hora($r->value);
            } elseif ($r->nodeType === XMLReader::END_ELEMENT) {
                if ($r->localName === 'time') {
                    $enTime = false;
                } elseif ($actual && ($r->localName === 'trkpt' || $r->localName === 'rtept')) {
                    $actual['lista'] === 'track'
                        ? self::agregar($track, $actual['lat'], $actual['lng'], $actual['t'])
                        : self::agregar($ruta, $actual['lat'], $actual['lng'], $actual['t']);
                    $actual = null;
                }
            }
        });

        return $track ?: $ruta;
    }

    private static function leerKml(string $contenido): array
    {
        $track = [];
        $linea = [];
        $pila = [];
        $whens = [];
        $k = 0;
        $texto = null;      // ['tipo' => 'when'|'coord'|'coordinates', 'valor' => '']

        self::recorrer($contenido, function (XMLReader $r) use (&$track, &$linea, &$pila, &$whens, &$k, &$texto) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                $padre = end($pila) ?: null;
                $nombre = $r->localName;
                if ($nombre === 'Track') {
                    $whens = [];
                    $k = 0;
                }
                if (! $r->isEmptyElement) {
                    $pila[] = $nombre;
                    if ($nombre === 'when' && $padre === 'Track') {
                        $texto = ['tipo' => 'when', 'valor' => ''];
                    } elseif ($nombre === 'coord' && $padre === 'Track') {
                        $texto = ['tipo' => 'coord', 'valor' => ''];
                    } elseif ($nombre === 'coordinates' && $padre === 'LineString') {
                        $texto = ['tipo' => 'coordinates', 'valor' => ''];
                    }
                }
            } elseif (($r->nodeType === XMLReader::TEXT || $r->nodeType === XMLReader::CDATA) && $texto) {
                $texto['valor'] .= $r->value;
            } elseif ($r->nodeType === XMLReader::END_ELEMENT) {
                array_pop($pila);
                if ($texto) {
                    $v = trim($texto['valor']);
                    if ($texto['tipo'] === 'when') {
                        $whens[] = self::hora($v);
                    } elseif ($texto['tipo'] === 'coord') {
                        $c = preg_split('/\s+/', $v);
                        self::agregar($track, $c[1] ?? null, $c[0] ?? null, $whens[$k] ?? null);
                        $k++;
                    } else {
                        foreach (preg_split('/\s+/', $v, -1, PREG_SPLIT_NO_EMPTY) as $tupla) {
                            $c = explode(',', $tupla);
                            self::agregar($linea, $c[1] ?? null, $c[0] ?? null, null);
                        }
                    }
                    $texto = null;
                }
            }
        });

        return $track ?: $linea;
    }

    private static function kmlDeKmz(string $binario): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'kmz');
        file_put_contents($tmp, $binario);
        $zip = new ZipArchive();
        $abierto = false;
        try {
            if ($zip->open($tmp) !== true) {
                throw new InvalidArgumentException('El KMZ no se pudo abrir.');
            }
            $abierto = true;
            $indice = $zip->locateName('doc.kml', ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR);
            if ($indice === false) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.kml')) {
                        $indice = $i;
                        break;
                    }
                }
            }
            if ($indice === false) {
                throw new InvalidArgumentException('El KMZ no trae ningún KML.');
            }
            if (($zip->statIndex($indice)['size'] ?? PHP_INT_MAX) > self::MAX_KML_EN_KMZ) {
                throw new InvalidArgumentException('El KML dentro del KMZ es demasiado grande.');
            }

            return (string) $zip->getFromIndex($indice);
        } finally {
            $abierto && $zip->close();
            @unlink($tmp);
        }
    }

    private static function agregar(array &$puntos, $lat, $lng, ?int $t): void
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) {
            return;
        }
        if (count($puntos) >= self::MAX_PUNTOS) {
            throw new InvalidArgumentException('La ruta trae más de ' . number_format(self::MAX_PUNTOS) . ' puntos.');
        }
        $puntos[] = [round($lat, 7), round($lng, 7), $t];
    }

    private static function hora(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        try {
            return Carbon::parse($valor, self::zona())->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function zona(): string
    {
        try {
            return (string) (config('app.timezone') ?: date_default_timezone_get());
        } catch (\Throwable) {
            return date_default_timezone_get();   // fuera de Laravel (pruebas puras)
        }
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public static function distancia(array $puntos): float
    {
        $total = 0.0;
        for ($i = 1, $n = count($puntos); $i < $n; $i++) {
            $total += self::haversine($puntos[$i - 1][0], $puntos[$i - 1][1], $puntos[$i][0], $puntos[$i][1]);
        }

        return $total;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null [minLat, minLng, maxLat, maxLng] */
    public static function bbox(array $puntos): ?array
    {
        if (! $puntos) {
            return null;
        }
        $lats = array_column($puntos, 0);
        $lngs = array_column($puntos, 1);

        return [min($lats), min($lngs), max($lats), max($lngs)];
    }

    /**
     * Línea simplificada [[lat, lng], …] para dibujar: filtro radial (quita el ruido de estar
     * parado) + Douglas-Peucker iterativo con tolerancia en metros. Si aún quedan más de
     * MAX_GEOMETRIA puntos, se duplica la tolerancia.
     */
    public static function simplificar(array $puntos, float $toleranciaM): array
    {
        $n = count($puntos);
        if ($n <= 2) {
            return array_map(fn ($p) => [$p[0], $p[1]], $puntos);
        }

        do {
            $res = self::douglasPeucker(self::filtroRadial($puntos, $toleranciaM / 2), $toleranciaM);
            $toleranciaM *= 2;
        } while (count($res) > self::MAX_GEOMETRIA);

        return $res;
    }

    private static function filtroRadial(array $puntos, float $radioM): array
    {
        $res = [$puntos[0]];
        $ultimo = $puntos[0];
        $n = count($puntos);
        for ($i = 1; $i < $n - 1; $i++) {
            if (self::haversine($ultimo[0], $ultimo[1], $puntos[$i][0], $puntos[$i][1]) >= $radioM) {
                $res[] = $puntos[$i];
                $ultimo = $puntos[$i];
            }
        }
        $res[] = $puntos[$n - 1];

        return $res;
    }

    private static function douglasPeucker(array $puntos, float $toleranciaM): array
    {
        $n = count($puntos);
        if ($n <= 2) {
            return array_map(fn ($p) => [$p[0], $p[1]], $puntos);
        }
        // Proyección equirectangular local: suficiente para distancias de una jornada.
        $lat0 = deg2rad(array_sum(array_column($puntos, 0)) / $n);
        $kx = 111320 * cos($lat0);
        $ky = 110540;
        $xy = array_map(fn ($p) => [$p[1] * $kx, $p[0] * $ky], $puntos);

        $conservar = array_fill(0, $n, false);
        $conservar[0] = $conservar[$n - 1] = true;
        $tol2 = $toleranciaM * $toleranciaM;
        $pila = [[0, $n - 1]];
        while ($pila) {
            [$a, $b] = array_pop($pila);
            $max = -1.0;
            $idx = -1;
            for ($i = $a + 1; $i < $b; $i++) {
                $d = self::distanciaSegmento2($xy[$i], $xy[$a], $xy[$b]);
                if ($d > $max) {
                    $max = $d;
                    $idx = $i;
                }
            }
            if ($idx !== -1 && $max > $tol2) {
                $conservar[$idx] = true;
                $pila[] = [$a, $idx];
                $pila[] = [$idx, $b];
            }
        }

        $res = [];
        foreach ($puntos as $i => $p) {
            if ($conservar[$i]) {
                $res[] = [$p[0], $p[1]];
            }
        }

        return $res;
    }

    private static function distanciaSegmento2(array $p, array $a, array $b): float
    {
        [$dx, $dy] = [$b[0] - $a[0], $b[1] - $a[1]];
        $largo2 = $dx * $dx + $dy * $dy;
        $t = $largo2 > 0 ? max(0, min(1, (($p[0] - $a[0]) * $dx + ($p[1] - $a[1]) * $dy) / $largo2)) : 0;
        $ex = $a[0] + $t * $dx - $p[0];
        $ey = $a[1] + $t * $dy - $p[1];

        return $ex * $ex + $ey * $ey;
    }

    /** Muestra pareja de la ruta completa para el recorte: [[idx, lat, lng, t|null], …]. */
    public static function muestra(array $puntos, int $tamano = self::TAMANO_MUESTRA): array
    {
        $n = count($puntos);
        if ($n === 0) {
            return [];
        }
        $paso = max(1, (int) ceil($n / $tamano));
        $res = [];
        for ($i = 0; $i < $n; $i += $paso) {
            $res[] = [$i, $puntos[$i][0], $puntos[$i][1], $puntos[$i][2]];
        }
        if ($res[count($res) - 1][0] !== $n - 1) {
            $res[] = [$n - 1, $puntos[$n - 1][0], $puntos[$n - 1][1], $puntos[$n - 1][2]];
        }

        return $res;
    }

    /**
     * Ubicación sobre la ruta a la hora $t (interpolada entre los dos puntos que la rodean).
     * Null si la ruta no trae horas o $t cae fuera de la ruta por más de $toleranciaS segundos.
     *
     * @return array{0: float, 1: float}|null
     */
    public static function ubicarPorHora(array $puntos, int $t, int $toleranciaS = 300): ?array
    {
        $conHora = array_values(array_filter($puntos, fn ($p) => $p[2] !== null));
        if (! $conHora) {
            return null;
        }
        usort($conHora, fn ($a, $b) => $a[2] <=> $b[2]);
        $primero = $conHora[0];
        $ultimo = $conHora[count($conHora) - 1];
        if ($t < $primero[2] - $toleranciaS || $t > $ultimo[2] + $toleranciaS) {
            return null;
        }
        if ($t <= $primero[2]) {
            return [$primero[0], $primero[1]];
        }
        if ($t >= $ultimo[2]) {
            return [$ultimo[0], $ultimo[1]];
        }
        foreach ($conHora as $i => $p) {
            $sig = $conHora[$i + 1] ?? null;
            if ($sig && $p[2] <= $t && $t <= $sig[2]) {
                $f = $sig[2] === $p[2] ? 0 : ($t - $p[2]) / ($sig[2] - $p[2]);

                return [round($p[0] + ($sig[0] - $p[0]) * $f, 7), round($p[1] + ($sig[1] - $p[1]) * $f, 7)];
            }
        }

        return null;
    }
}
