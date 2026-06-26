<?php

namespace App\Modules\Addons\PortalPago\Services\Cep;

/**
 * Resultado de una validación CEP. Inmutable.
 *
 * Estados producidos por el DRIVER:
 *   - INCONCLUSIVE : red caída / no-200 / XML no parseable / sin nodo de
 *                    liquidación. → el reporte queda en pendiente_validacion.
 *   - NOT_FOUND    : Banxico respondió pero el pago no existe o su estado no es
 *                    LIQUIDADO. → el reporte queda en discrepancia.
 *   - FOUND        : Banxico devolvió un CEP liquidado con datos extraídos. El
 *                    SERVICIO ORQUESTADOR decide si es validado o discrepancia.
 *
 * Estados producidos por el ORQUESTADOR (tras el match de 3 condiciones):
 *   - VALIDATED    : monto exacto + CLABE nuestra + estado LIQUIDADO.
 *   - DISCREPANCY  : encontrado pero algo no cuadra (monto/CLABE).
 */
final class CepValidationResult
{
    public const INCONCLUSIVE = 'inconclusive';
    public const NOT_FOUND    = 'not_found';
    public const FOUND        = 'found';
    public const VALIDATED    = 'validated';
    public const DISCREPANCY  = 'discrepancy';

    private function __construct(
        public readonly string $state,
        public readonly string $message,
        public readonly ?string $cepEstado = null,            // p.ej. 'LIQUIDADO'
        public readonly ?string $cepMonto = null,             // decimal string
        public readonly ?string $cepClabeBeneficiaria = null,
        public readonly ?string $xml = null,                  // XML crudo del CEP
        public readonly array $raw = []                       // se guarda en cep_resultado (json)
    ) {
    }

    public static function inconclusive(string $message, array $raw = []): self
    {
        return new self(self::INCONCLUSIVE, $message, raw: $raw);
    }

    public static function notFound(string $message, ?string $cepEstado = null, array $raw = []): self
    {
        return new self(self::NOT_FOUND, $message, cepEstado: $cepEstado, raw: $raw);
    }

    public static function found(
        string $cepEstado,
        ?string $cepMonto,
        ?string $cepClabeBeneficiaria,
        ?string $xml,
        array $raw = []
    ): self {
        return new self(
            self::FOUND,
            'CEP liquidado encontrado.',
            cepEstado: $cepEstado,
            cepMonto: $cepMonto,
            cepClabeBeneficiaria: $cepClabeBeneficiaria,
            xml: $xml,
            raw: $raw
        );
    }

    /** Producido por el orquestador a partir de un FOUND que cuadra en las 3 condiciones. */
    public static function validated(self $found, string $message = 'Pago validado contra CEP.'): self
    {
        return new self(
            self::VALIDATED,
            $message,
            cepEstado: $found->cepEstado,
            cepMonto: $found->cepMonto,
            cepClabeBeneficiaria: $found->cepClabeBeneficiaria,
            xml: $found->xml,
            raw: $found->raw
        );
    }

    /** Producido por el orquestador cuando el CEP existe pero monto/CLABE no cuadran. */
    public static function discrepancy(self $found, string $message): self
    {
        return new self(
            self::DISCREPANCY,
            $message,
            cepEstado: $found->cepEstado,
            cepMonto: $found->cepMonto,
            cepClabeBeneficiaria: $found->cepClabeBeneficiaria,
            xml: $found->xml,
            raw: $found->raw
        );
    }

    public function isInconclusive(): bool { return $this->state === self::INCONCLUSIVE; }
    public function isNotFound(): bool     { return $this->state === self::NOT_FOUND; }
    public function isFound(): bool        { return $this->state === self::FOUND; }
    public function isValidated(): bool    { return $this->state === self::VALIDATED; }
    public function isDiscrepancy(): bool  { return $this->state === self::DISCREPANCY; }

    public function toArray(): array
    {
        return [
            'state'                  => $this->state,
            'message'                => $this->message,
            'cep_estado'             => $this->cepEstado,
            'cep_monto'              => $this->cepMonto,
            'cep_clabe_beneficiaria' => $this->cepClabeBeneficiaria,
            'raw'                    => $this->raw,
        ];
    }
}
