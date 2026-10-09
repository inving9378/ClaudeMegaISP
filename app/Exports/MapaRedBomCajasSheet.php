<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class MapaRedBomCajasSheet implements FromArray, WithTitle
{
    public function __construct(private readonly array $cajas)
    {
    }

    public function title(): string
    {
        return 'Cajas';
    }

    public function array(): array
    {
        $filas = [['Tipo', 'Cantidad']];

        foreach ($this->cajas as $c) {
            $filas[] = [$c['tipo'], $c['cantidad']];
        }

        return $filas;
    }
}
