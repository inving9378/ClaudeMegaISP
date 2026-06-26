<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Contrato de un driver de validación CEP. Una implementación NUNCA debe lanzar
 * excepciones que tumben el flujo de conciliación: ante cualquier fallo debe
 * devolver CepValidationResult::inconclusive().
 */
interface CepValidatorDriver
{
    public function validate(CepQuery $query): CepValidationResult;

    /** Identificador del driver para logs/auditoría (p.ej. 'banxico', 'manual'). */
    public function name(): string;
}
