<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Contracts;


/**
 * Lo que un resolvedor devuelve al resolver un concepto.
 *
 * `metricas` es lo que alimenta el porcentaje de completitud del apartado; el
 * resto es para pintar. Un concepto sin fuente NUNCA devuelve una vista vacía
 * ni una excepción: devuelve `sin_fuente` con un mensaje que lo explica.
 */
class ResultadoConcepto
{
    /** Los datos están completos y son confiables. */
    public const RESUELTO = 'resuelto';
    /** Hay algo, pero no todo lo que el concepto exige. */
    public const PARCIAL = 'parcial';
    /** No hay de dónde leerlo: falta configurar la fuente o subir el documento. */
    public const SIN_FUENTE = 'sin_fuente';
    /** La fuente existe y respondió, pero no tiene registros. */
    public const VACIO = 'vacio';

    public const ESTADOS = [self::RESUELTO, self::PARCIAL, self::SIN_FUENTE, self::VACIO];

    public function __construct(
        public readonly string $estado,
        public readonly string $vista,
        public readonly array $datos = [],
        public readonly array $metricas = [],
        public readonly ?string $mensaje = null,
    ) {
    }

    public static function resuelto(string $vista, array $datos, array $metricas = []): self
    {
        return new self(self::RESUELTO, $vista, $datos, $metricas + ['resuelto' => true]);
    }

    public static function parcial(string $vista, array $datos, string $mensaje, array $metricas = []): self
    {
        return new self(self::PARCIAL, $vista, $datos, $metricas + ['resuelto' => false], $mensaje);
    }

    public static function vacio(string $vista, string $mensaje, array $metricas = []): self
    {
        return new self(self::VACIO, $vista, [], $metricas + ['resuelto' => false], $mensaje);
    }

    public static function sinFuente(string $mensaje, array $metricas = []): self
    {
        return new self(
            self::SIN_FUENTE,
            'dc-concepto-sin-fuente',
            [],
            $metricas + ['resuelto' => false],
            $mensaje
        );
    }

    /** ¿Este resultado cuenta como resuelto para el % de completitud? */
    public function cuentaComoResuelto(): bool
    {
        return (bool) ($this->metricas['resuelto'] ?? false);
    }

    public function toArray(): array
    {
        return [
            'estado'   => $this->estado,
            'vista'    => $this->vista,
            'datos'    => $this->datos,
            'metricas' => $this->metricas,
            'mensaje'  => $this->mensaje,
        ];
    }
}
