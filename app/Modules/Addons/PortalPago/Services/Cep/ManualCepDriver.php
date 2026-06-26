<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Driver de validación manual: NUNCA contacta a Banxico. Todo reporte se deja
 * inconcluso para que el equipo lo revise y apruebe/rechace a mano desde la
 * bandeja de conciliación. Útil en modo 'manual' o como respaldo cuando el
 * servicio de Banxico está caído/bloqueado.
 */
class ManualCepDriver implements CepValidatorDriver
{
    public function name(): string
    {
        return 'manual';
    }

    public function validate(CepQuery $query): CepValidationResult
    {
        return CepValidationResult::inconclusive(
            'Modo manual: el reporte queda pendiente de validación para revisión del equipo.',
            ['driver' => 'manual', 'query' => $query->toLogContext()]
        );
    }
}
