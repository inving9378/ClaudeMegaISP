<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Driver de validación manual: NUNCA contacta a Banxico. Todo reporte se deja
 * inconcluso para que el equipo lo revise y apruebe/rechace a mano desde la
 * bandeja de conciliación. Solo se usa cuando `PAGOS_CEP_MODE=manual`
 * (ver CepValidatorService::driverForMode()) — NO es un fallback automático:
 * cuando Banxico está caído/bloqueado, es BanxicoCepDriver quien degrada a
 * inconclusive() por sí mismo (modos 'banxico'/'hybrid'), sin pasar por aquí.
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
