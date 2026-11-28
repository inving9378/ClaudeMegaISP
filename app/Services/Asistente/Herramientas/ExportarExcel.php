<?php

namespace App\Services\Asistente\Herramientas;

use App\Models\User;
use App\Services\Asistente\AdjuntosAsistente;
use App\Services\Asistente\Datos\ConsultaRechazada;
use App\Services\Asistente\Datos\LectorBaseDatos;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * El resultado completo de una consulta como Excel para descargar (también cruces con los Excel
 * adjuntos). El archivo se borra solo, igual que los adjuntos.
 */
class ExportarExcel extends Herramienta
{
    use UsaArchivos;

    private const MAX_FILAS = 100000;

    /** Arriba de esto se entrega CSV: un .xlsx tan grande no cabe en memoria al armarse. */
    private const MAX_FILAS_XLSX = 30000;

    public function nombre(): string
    {
        return 'exportar_excel';
    }

    public function descripcion(): string
    {
        return 'Genera un Excel para descargar con el resultado COMPLETO de una consulta (las mismas reglas que consultar_datos, '
            . 'hasta ' . number_format(self::MAX_FILAS) . ' filas), incluso cruzando los Excel adjuntos con el sistema. Úsala cuando te pidan '
            . 'un listado, un reporte o «pásamelo en Excel», o cuando el resultado tenga más filas de las que caben en el chat. '
            . 'Pon nombres de columna claros en español con AS. Devuelve el enlace de descarga: compártelo tal cual.';
    }

    public function permiso(): string|array|null
    {
        return ExplorarDatos::PERMISO;
    }

    public function parametros(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sql'      => ['type' => 'string', 'description' => 'La consulta: una sola instrucción SELECT o WITH, con alias claros en español.'],
                'nombre'   => ['type' => 'string', 'description' => 'Nombre del archivo, sin extensión. Ej.: «Clientes con adeudo noviembre».'],
                'archivos' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Números de los Excel/CSV adjuntos que la consulta usa como tablas.'],
            ],
            'required' => ['sql', 'nombre'],
        ];
    }

    public function ejecutar(User $user, array $args): array
    {
        @set_time_limit(300);
        try {
            $r = app(LectorBaseDatos::class)->consultar((string) ($args['sql'] ?? ''), $this->hojasDe($user, $args['archivos'] ?? []), self::MAX_FILAS, 32000, 90);
        } catch (ConsultaRechazada $e) {
            return ['error' => $e->getMessage()];
        }
        if ($r['total'] === 0) {
            return ['error' => 'La consulta no trajo ninguna fila; no hay nada que exportar.'];
        }

        $base = trim(preg_replace('/[^\pL\pN ._-]+/u', ' ', (string) ($args['nombre'] ?? ''))) ?: 'Resultado';
        $base = Str::limit($base, 80, '');
        $comoCsv = $r['total'] > self::MAX_FILAS_XLSX;
        $nombre = $base . ($comoCsv ? '.csv' : '.xlsx');

        $adjunto = app(AdjuntosAsistente::class)->crearGenerado($user, $this->conversacionDe($user), $nombre, function (string $destino) use ($r, $comoCsv, $base) {
            $comoCsv ? $this->escribirCsv($destino, $r) : $this->escribirXlsx($destino, $r, $base);
        });
        Log::info('[JARVIS] exportó un Excel', ['user' => $user->id, 'adjunto' => $adjunto->id, 'filas' => $r['total']]);

        $enlace = "/ia/asistente/adjuntos/{$adjunto->id}/descargar";

        return array_filter([
            'ok'      => true,
            'archivo' => $nombre,
            'filas'   => $r['total'],
            'enlace'  => $enlace,
            'nota'    => "Comparte el enlace así: [Descargar {$nombre}]({$enlace}). Se borra solo en unas horas."
                . ($r['truncado'] ? ' Ojo: se cortó en ' . number_format(self::MAX_FILAS) . ' filas.' : '')
                . ($comoCsv ? ' Por su tamaño va en CSV (se abre en Excel).' : ''),
        ]);
    }

    private function escribirXlsx(string $destino, array $r, string $titulo): void
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(Str::limit(preg_replace('#[\\\\/?*\[\]:]+#', ' ', $titulo), 31, '') ?: 'Resultado');
        $hoja->fromArray($r['columnas'], null, 'A1');
        if ($r['filas']) {
            $hoja->fromArray($r['filas'], null, 'A2', true);
        }

        $ultima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($r['columnas']));
        $hoja->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $hoja->getStyle("A1:{$ultima}1")->getFill()->setFillType('solid')->getStartColor()->setRGB('EDE9FE');
        $hoja->freezePane('A2');
        $hoja->setAutoFilter("A1:{$ultima}" . (count($r['filas']) + 1));
        foreach (range(1, min(count($r['columnas']), 40)) as $i) {
            $hoja->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        (new Xlsx($libro))->save($destino);
        $libro->disconnectWorksheets();
    }

    private function escribirCsv(string $destino, array $r): void
    {
        $f = fopen($destino, 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $r['columnas']);
        foreach ($r['filas'] as $fila) {
            fputcsv($f, $fila);
        }
        fclose($f);
    }
}
