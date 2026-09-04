<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Excel agregado de un apartado completo: todos sus conceptos ya resueltos en
 * una sola hoja, separados por encabezado de concepto (más simple que una
 * hoja por concepto vía WithMultipleSheets — evita sanitizar títulos de hoja
 * contra el límite de 31 caracteres de Excel para ~10 conceptos por apartado).
 *
 * Concepto sin datos ('sin fuente' o resultado vacío) muestra "Sin registros
 * en este entorno" en vez de una hoja rota — mismo criterio que
 * `BaseResolver::escribirCsv()`.
 */
class DocumentacionApartadoExport implements FromArray, WithTitle
{
    public function __construct(
        private readonly string $apartadoClave,
        private readonly array $conceptos,
    ) {
    }

    public function title(): string
    {
        return Str::limit('Apartado ' . $this->apartadoClave, 31, '');
    }

    public function array(): array
    {
        $filas = [];

        foreach ($this->conceptos as $concepto) {
            $filas[] = [$concepto['nombre'] ?? ''];

            $datos = array_values(array_filter($concepto['datos'] ?? [], 'is_array'));

            if ($datos === []) {
                $filas[] = ['Sin registros en este entorno'];
            } else {
                $filas[] = array_keys($datos[0]);

                foreach ($datos as $fila) {
                    $filas[] = array_map(
                        fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v, JSON_UNESCAPED_UNICODE),
                        array_values($fila)
                    );
                }
            }

            $filas[] = [];
        }

        return $filas;
    }
}
