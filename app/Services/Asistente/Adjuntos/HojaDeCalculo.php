<?php

namespace App\Services\Asistente\Adjuntos;

use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Convierte un Excel/CSV/ODS en hojas de columnas + filas que JARVIS puede leer y cruzar con el
 * sistema. Cada columna lleva un identificador limpio (sin acentos ni espacios) para poder usarla
 * en consultas, y su título original para hablar con el usuario.
 *
 * Los valores se guardan crudos: números como números (sin formato de moneda) y fechas como
 * año-mes-día, para que sumar, filtrar y comparar funcione igual que en el sistema.
 */
class HojaDeCalculo
{
    public const MAX_FILAS = 100000;

    public const MAX_COLUMNAS = 80;

    public const MAX_CARACTERES_CELDA = 2000;

    /**
     * @return array<int, array{nombre:string, columnas:array<int, array{id:string, titulo:string, tipo:string}>, filas:array<int, array>, recortada:bool}>
     */
    public function leer(string $ruta, string $extension): array
    {
        $extension = strtolower($extension);

        $crudas = in_array($extension, ['csv', 'tsv', 'txt'], true)
            ? [['nombre' => 'Hoja 1', 'filas' => $this->leerTexto($ruta, $extension === 'tsv' ? "\t" : null), 'fechas' => []]]
            : $this->leerLibro($ruta);

        $hojas = [];
        foreach ($crudas as $cruda) {
            $hoja = $this->armar($cruda['nombre'], $cruda['filas'], $cruda['fechas']);
            if ($hoja !== null) {
                $hojas[] = $hoja;
            }
        }

        return $hojas;
    }

    /** @return array<int, array{nombre:string, filas:array, fechas:array<int,bool>}> */
    private function leerLibro(string $ruta): array
    {
        $lector = IOFactory::createReaderForFile($ruta);
        // Sin solo-datos: hace falta el formato de cada celda para saber cuáles son fechas.
        if (method_exists($lector, 'setReadEmptyCells')) {
            $lector->setReadEmptyCells(false);
        }
        $libro = $lector->load($ruta);

        $hojas = [];
        foreach ($libro->getWorksheetIterator() as $hoja) {
            $ultimaFila = min($hoja->getHighestDataRow(), self::MAX_FILAS + 1);
            $ultimaColumna = min(Coordinate::columnIndexFromString($hoja->getHighestDataColumn()), self::MAX_COLUMNAS);
            if ($ultimaFila < 1 || $ultimaColumna < 1) {
                continue;
            }

            $rango = 'A1:' . Coordinate::stringFromColumnIndex($ultimaColumna) . $ultimaFila;
            $filas = $hoja->rangeToArray($rango, null, true, false, false);

            // Columnas con formato de fecha: se revisa la primera celda con número de cada columna.
            $fechas = [];
            for ($c = 1; $c <= $ultimaColumna; $c++) {
                for ($f = 2; $f <= min($ultimaFila, 60); $f++) {
                    $celda = $hoja->getCell(Coordinate::stringFromColumnIndex($c) . $f);
                    $valor = $celda->getValue();
                    if ($valor === null || $valor === '') {
                        continue;
                    }
                    if (is_numeric($valor) && FechaExcel::isDateTime($celda)) {
                        $fechas[$c - 1] = true;
                    }
                    break;
                }
            }

            $hojas[] = ['nombre' => (string) $hoja->getTitle(), 'filas' => $filas, 'fechas' => $fechas];
        }
        $libro->disconnectWorksheets();

        return $hojas;
    }

    /** CSV con separador y codificación detectados (los de Excel en español suelen venir con ; y en Windows-1252). */
    private function leerTexto(string $ruta, ?string $separador): array
    {
        $contenido = (string) file_get_contents($ruta);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        if ($separador === null) {
            $primera = strtok($contenido, "\n") ?: '';
            $separador = ',';
            $mejor = 0;
            foreach ([',', ';', "\t", '|'] as $s) {
                if (substr_count($primera, $s) > $mejor) {
                    $mejor = substr_count($primera, $s);
                    $separador = $s;
                }
            }
        }

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);

        $filas = [];
        while (($fila = fgetcsv($flujo, 0, $separador)) !== false) {
            if (count($filas) > self::MAX_FILAS) {
                break;
            }
            $filas[] = array_slice($fila, 0, self::MAX_COLUMNAS);
        }
        fclose($flujo);

        return $filas;
    }

    private function armar(string $nombre, array $filas, array $fechas): ?array
    {
        // Sin renglones vacíos (ni al principio, ni en medio, ni al final).
        $filas = array_values(array_filter($filas, fn ($f) => is_array($f) && count(array_filter($f, fn ($v) => $v !== null && trim((string) $v) !== '')) > 0));
        if (!$filas) {
            return null;
        }

        $ancho = 0;
        foreach ($filas as $f) {
            for ($i = count($f) - 1; $i >= 0; $i--) {
                if ($f[$i] !== null && trim((string) $f[$i]) !== '') {
                    $ancho = max($ancho, $i + 1);
                    break;
                }
            }
        }
        $ancho = min($ancho, self::MAX_COLUMNAS);

        // ¿El primer renglón son títulos? Si la mitad o más de sus celdas son números, no.
        $primera = array_slice(array_pad($filas[0], $ancho, null), 0, $ancho);
        $llenas = array_filter($primera, fn ($v) => $v !== null && trim((string) $v) !== '');
        $numericas = array_filter($llenas, fn ($v) => is_numeric($v));
        $conTitulos = $llenas && count($numericas) < count($llenas) / 2;

        $titulos = [];
        for ($i = 0; $i < $ancho; $i++) {
            $t = $conTitulos ? trim((string) ($primera[$i] ?? '')) : '';
            $titulos[] = $t !== '' ? $t : 'Columna ' . ($i + 1);
        }
        $datos = $conTitulos ? array_slice($filas, 1) : $filas;

        $recortada = count($datos) > self::MAX_FILAS;
        $datos = array_slice($datos, 0, self::MAX_FILAS);

        $ids = self::identificadores($titulos);
        $limpias = [];
        foreach ($datos as $f) {
            $fila = [];
            for ($i = 0; $i < $ancho; $i++) {
                $fila[] = $this->valor($f[$i] ?? null, !empty($fechas[$i]));
            }
            $limpias[] = $fila;
        }

        $columnas = [];
        foreach ($ids as $i => $id) {
            $columnas[] = ['id' => $id, 'titulo' => $titulos[$i], 'tipo' => $this->tipoDeColumna(array_column($limpias, $i), !empty($fechas[$i]))];
        }

        return ['nombre' => $nombre, 'columnas' => $columnas, 'filas' => $limpias, 'recortada' => $recortada];
    }

    private function valor(mixed $v, bool $esFecha): mixed
    {
        if ($v === null) {
            return null;
        }
        if ($esFecha && is_numeric($v)) {
            try {
                $d = FechaExcel::excelToDateTimeObject((float) $v);

                return fmod((float) $v, 1.0) > 0 ? $d->format('Y-m-d H:i:s') : $d->format('Y-m-d');
            } catch (\Throwable) {
                return $v;
            }
        }
        if (is_bool($v)) {
            return $v ? 1 : 0;
        }
        if (is_int($v) || is_float($v)) {
            return is_float($v) && !is_finite($v) ? null : $v;
        }

        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, self::MAX_CARACTERES_CELDA);
    }

    private function tipoDeColumna(array $valores, bool $esFecha): string
    {
        $llenos = array_filter($valores, fn ($v) => $v !== null && $v !== '');
        if (!$llenos) {
            return 'texto';
        }
        if ($esFecha) {
            return 'fecha';
        }
        $numeros = array_filter($llenos, fn ($v) => is_int($v) || is_float($v));
        if (count($numeros) === count($llenos)) {
            return 'numero';
        }
        $fechas = array_filter($llenos, fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v));

        return count($fechas) === count($llenos) ? 'fecha' : 'texto';
    }

    /** Títulos → identificadores únicos aptos para consultas: «Núm. de cliente» → num_de_cliente. */
    public static function identificadores(array $titulos): array
    {
        $usados = ['_fila' => true];
        $ids = [];
        foreach (array_values($titulos) as $i => $titulo) {
            $id = Str::of(Str::ascii((string) $titulo))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->substr(0, 50)->value();
            if ($id === '' || str_starts_with($id, 'columna_')) {
                $id = 'col_' . ($i + 1);
            }
            if (ctype_digit($id[0])) {
                $id = 'c_' . $id;
            }
            $base = $id;
            $n = 2;
            while (isset($usados[$id])) {
                $id = $base . '_' . $n++;
            }
            $usados[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }
}
