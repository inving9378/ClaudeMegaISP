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
        //obtener IVA: lo que falta para llegar al total, así Cantidad + IVA = Total al centavo.
        //Redondear Tasa * Cantidad por separado perdía o sumaba un centavo ($99 salía en $98.99);
        //la diferencia contra Tasa * Cantidad es de menos de un centavo, dentro de la tolerancia del SAT.
        $iva = round($this->total - $cantidad, 2);

        return [
            'iva' => $iva,
            'monto' => $cantidad,
        ];
    }
}
