<?php

namespace App\Modules\Addons\MapaRed\Services;

use Illuminate\Support\Str;

/**
 * MR-25 Fase 3b (item #9990444) — parseo de CSV para el mismo contrato preview/commit de
 * `ImportadorRedService`. A diferencia de KML/GeoJSON, un CSV no trae esquema fijo: las
 * columnas lat/lng/nombre/tipo/descripción se detectan por el NOMBRE del encabezado (decisión
 * de Irving, opción "auto-detección de columnas" — sin wizard de mapeo manual columna→campo).
 *
 * Alcance de esta fase (decisión de Irving, q3): solo nodos/POPs — cada fila del CSV es un
 * único punto (marker), nunca una línea/polígono (un CSV plano no puede representar eso sin
 * una convención extra fuera de alcance aquí).
 *
 * Política de errores (decisión de Irving, q2): se valida TODO el archivo antes de devolver el
 * preview. Si CUALQUIER fila tiene datos inválidos, se aborta el preview completo y se
 * devuelve el reporte de errores por fila — no hay importación parcial de "lo que sí sirve".
 */
class CsvParserService
{
    /**
     * Encabezados aceptados por campo (normalizados sin acentos/mayúsculas, comparación exacta
     * contra el encabezado ya normalizado). Primer match gana.
     */
    private const ENCABEZADOS = [
        'lat' => ['lat', 'latitud', 'latitude', 'y'],
        'lng' => ['lng', 'lon', 'long', 'longitud', 'longitude', 'x'],
        'nombre' => ['nombre', 'name', 'title', 'titulo'],
        'tipo' => ['tipo', 'type', 'categoria', 'category'],
        'descripcion' => ['descripcion', 'description', 'notas', 'notes'],
    ];

    /**
     * @return array{placemarks: array, errores: array, columnas_detectadas: array}
     */
    public static function parsearArchivo(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException('No se pudo leer el archivo CSV');
        }

        $encabezados = fgetcsv($handle);
        if ($encabezados === false || $encabezados === null) {
            fclose($handle);
            throw new \InvalidArgumentException('El archivo CSV está vacío');
        }

        $mapeo = self::detectarColumnas($encabezados);
        if ($mapeo['lat'] === null || $mapeo['lng'] === null) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'No se detectaron columnas de latitud/longitud en el CSV (encabezados esperados: lat/latitud, lng/lon/longitud)'
            );
        }

        $placemarks = [];
        $errores = [];
        $numeroFila = 1; // fila 1 = encabezados

        while (($fila = fgetcsv($handle)) !== false) {
            $numeroFila++;
            if (self::filaVacia($fila)) {
                continue;
            }

            $latCruda = trim((string) self::valor($fila, $mapeo['lat']));
            $lngCruda = trim((string) self::valor($fila, $mapeo['lng']));

            if ($latCruda === '' || $lngCruda === '' || !is_numeric($latCruda) || !is_numeric($lngCruda)) {
                $errores[] = [
                    'fila' => $numeroFila,
                    'motivo' => 'Latitud/longitud no numéricas o vacías',
                    'valores' => ['lat' => $latCruda, 'lng' => $lngCruda],
                ];
                continue;
            }

            $lat = (float) $latCruda;
            $lng = (float) $lngCruda;
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                $errores[] = [
                    'fila' => $numeroFila,
                    'motivo' => 'Latitud/longitud fuera de rango',
                    'valores' => ['lat' => $lat, 'lng' => $lng],
                ];
                continue;
            }

            $nombre = $mapeo['nombre'] !== null ? trim((string) self::valor($fila, $mapeo['nombre'])) : '';
            $tipoHint = $mapeo['tipo'] !== null ? trim((string) self::valor($fila, $mapeo['tipo'])) : null;
            $descripcion = $mapeo['descripcion'] !== null ? trim((string) self::valor($fila, $mapeo['descripcion'])) : null;

            $placemarks[] = [
                'name' => $nombre,
                'type' => 'marker',
                'coords' => ['lat' => $lat, 'lng' => $lng],
                'folder_path' => [],
                'data' => ['description' => $descripcion !== '' ? $descripcion : null],
                'extended_data' => [],
                'tipo_hint' => ($tipoHint !== null && $tipoHint !== '') ? $tipoHint : null,
            ];
        }

        fclose($handle);

        return [
            'placemarks' => $placemarks,
            'errores' => $errores,
            'columnas_detectadas' => $mapeo,
        ];
    }

    private static function detectarColumnas(array $encabezados): array
    {
        $normalizados = array_map(fn($h) => self::normalizarTexto((string) $h), $encabezados);
        $mapeo = ['lat' => null, 'lng' => null, 'nombre' => null, 'tipo' => null, 'descripcion' => null];

        foreach (self::ENCABEZADOS as $campo => $candidatos) {
            foreach ($normalizados as $indice => $encabezado) {
                if (in_array($encabezado, $candidatos, true)) {
                    $mapeo[$campo] = $indice;
                    break;
                }
            }
        }

        return $mapeo;
    }

    private static function valor(array $fila, ?int $indice): ?string
    {
        if ($indice === null) {
            return null;
        }

        return $fila[$indice] ?? null;
    }

    private static function filaVacia(array $fila): bool
    {
        foreach ($fila as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function normalizarTexto(string $texto): string
    {
        return trim(mb_strtolower(Str::ascii($texto)));
    }
}
