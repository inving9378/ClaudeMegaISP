<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Tupla de consulta a CEP de Banxico. Inmutable.
 *
 * Los importes se manejan como STRING decimal con 2 decimales para evitar
 * errores de coma flotante en la comparación de montos.
 */
final class CepQuery
{
    public function __construct(
        public readonly string $fecha,                // dd/mm/YYYY
        public readonly string $claveRastreo,
        public readonly string $bancoEmisor,          // clave Banxico del emisor
        public readonly string $bancoReceptor,        // clave Banxico del receptor
        public readonly string $cuentaBeneficiaria,   // CLABE nuestra (portal_pago_accounts)
        public readonly string $monto                 // decimal string, 2 decimales
    ) {
    }

    public function toFormParams(): array
    {
        return [
            'tipoCriterio' => 'T',
            'fecha'        => $this->fecha,
            'criterio'     => $this->claveRastreo,
            'emisor'       => $this->bancoEmisor,
            'receptor'     => $this->bancoReceptor,
            'cuenta'       => $this->cuentaBeneficiaria,
            'monto'        => $this->monto,
            'receptorParticipante' => '0',
        ];
    }

    public function toLogContext(): array
    {
        return [
            'fecha'         => $this->fecha,
            'clave_rastreo' => $this->claveRastreo,
            'emisor'        => $this->bancoEmisor,
            'receptor'      => $this->bancoReceptor,
            'cuenta'        => $this->cuentaBeneficiaria,
            'monto'         => $this->monto,
        ];
    }
}
