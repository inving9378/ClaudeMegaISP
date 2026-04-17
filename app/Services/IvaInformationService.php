<?php

namespace App\Services;

class IvaInformationService
{
    protected $tasa;
    protected $total;
    public function __construct($tasa,$total)
    {
        $this->tasa = $tasa;
        $this->total = $total;
    }

    public function getIvaInformation()
    {
        //https://www.calculadorasat.com/calcular-iva
        /* Las formulas que se usan son las del calculo de porcentajes.
            La formula para obtener el IVA es:
            Tasa / 100 * Cantidad = IVA
            Las formulas para obtener el total a pagar son:
            Cantidad + IVA = Total
            Cantidad * (1+(Tasa/100)) = Total
            */
        //obtener cantidad redondeada
        $cantidad = $this->total / (1 + ($this->tasa / 100));
        $cantidad = round($cantidad, 2);
        //obtener IVA
        $iva = $this->tasa / 100 * $cantidad;
        $iva = round($iva, 2);

        return [
            'iva' => $iva,
            'monto' => $cantidad,
        ];
    }
}
