<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class MapaRedBomCablesSheet implements FromArray, WithTitle
{
    public function __construct(private readonly array $cables)
    {
    }

    public function title(): string
    {
        return 'Cables';
    }

    public function array(): array
    {
        $filas = [['Tipo', 'Tramos', 'Longitud aprox. (m)', 'Precio unitario', 'Subtotal', 'Nota']];

        foreach ($this->cables as $c) {
            $filas[] = [
                $c['tipo'],
                $c['cantidad_tramos'],
                $c['longitud_metros_aprox'],
                $c['precio_unitario'] ?? 'N/D',
                $c['subtotal'] ?? 'N/D',
                $c['precio_nota'] ?? '',
            ];
        }

        return $filas;
    }
}
