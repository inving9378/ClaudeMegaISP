<?php

namespace App\Modules\Addons\Roadmap\Services\ConsolaAcciones;

use InvalidArgumentException;

/**
 * Item #876 — declaración de UNA acción de la Consola de la Torre.
 *
 * Una acción es DATOS: clave, permiso, si confirma y cómo, si es reversible, qué registra.
 * `ConsolaAccionExecutor` es el único que la ejecuta — ningún botón habla directo con su
 * servicio. Las reglas de la sección 2 del item se hacen cumplir AQUÍ, al declarar, no al
 * ejecutar: una combinación insegura no llega a existir en el catálogo.
 */
final class ConsolaAccion
{
    public const CONFIRMA_NO = 'no';
    public const CONFIRMA_SI = 'si';
    public const CONFIRMA_SI_CON_TEXTO = 'si_con_texto';

    private const CONFIRMA_VALIDAS = [self::CONFIRMA_NO, self::CONFIRMA_SI, self::CONFIRMA_SI_CON_TEXTO];

    /**
     * Claves prohibidas (sección 5 del item): el catálogo no puede declarar una acción que toque
     * la separación dev/prod, `migrate:fresh`, `git add -A`, el `guard()` de la vía externa o los
     * topes duros. Heurística sobre la CLAVE (el identificador que el desarrollador elige) — capa
     * de defensa, no análisis estático del handler; el mecanismo determinista es `toca_guardrail`
     * más abajo.
     */
    private const PATRON_CLAVE_PROHIBIDA = '/prod|migrate\.?fresh|git\.?add.?all|guard\.?disable|tope.?duro/i';

    public readonly string $clave;
    public readonly string $nombre;
    public readonly string $descripcion;
    public readonly string $permiso;
    public readonly string $confirma;
    public readonly ?string $advertencia;
    public readonly bool $reversible;
    public readonly bool $simulaPrimero;
    public readonly string $registra;
    public readonly string $respuesta;

    public function __construct(array $def)
    {
        foreach (['clave', 'nombre', 'descripcion', 'permiso', 'registra', 'respuesta'] as $campo) {
            if (empty($def[$campo]) || ! is_string($def[$campo])) {
                throw new InvalidArgumentException("ConsolaAccion: falta el atributo obligatorio '{$campo}'.");
            }
        }

        $clave = $def['clave'];

        if (! empty($def['toca_guardrail'])) {
            throw new InvalidArgumentException(
                "ConsolaAccion '{$clave}': declarada con toca_guardrail=true. El catálogo NO puede declarar " .
                'acciones que toquen la separación dev/prod, migrate:fresh, git add -A, el guard() de la vía ' .
                'externa ni los topes duros (item #876, sección 5). No es una convención: esto es lo que lo ' .
                'hace imposible.'
            );
        }

        if (preg_match(self::PATRON_CLAVE_PROHIBIDA, $clave)) {
            throw new InvalidArgumentException(
                "ConsolaAccion '{$clave}': la clave coincide con un patrón protegido (item #876, sección 5). " .
                'Elige otra clave; si la acción de verdad toca un guardrail, no se declara.'
            );
        }

        $confirma = $def['confirma'] ?? self::CONFIRMA_NO;
        if (! in_array($confirma, self::CONFIRMA_VALIDAS, true)) {
            throw new InvalidArgumentException(
                "ConsolaAccion '{$clave}': confirma debe ser 'no', 'si' o 'si_con_texto', recibido '{$confirma}'."
            );
        }

        $reversible = array_key_exists('reversible', $def) ? (bool) $def['reversible'] : true;

        if (! $reversible && $confirma === self::CONFIRMA_NO) {
            throw new InvalidArgumentException(
                "ConsolaAccion '{$clave}': reversible=false exige confirma distinto de 'no' (item #876, regla 2). " .
                'Una acción irreversible sin confirmación es exactamente la incoherencia que este catálogo hace imposible.'
            );
        }

        if ($confirma !== self::CONFIRMA_NO && empty($def['advertencia'])) {
            throw new InvalidArgumentException(
                "ConsolaAccion '{$clave}': confirma='{$confirma}' exige 'advertencia' (el texto del diálogo)."
            );
        }

        $this->clave = $clave;
        $this->nombre = $def['nombre'];
        $this->descripcion = $def['descripcion'];
        $this->permiso = $def['permiso'];
        $this->confirma = $confirma;
        $this->advertencia = $def['advertencia'] ?? null;
        $this->reversible = $reversible;
        $this->simulaPrimero = (bool) ($def['simula_primero'] ?? false);
        $this->registra = $def['registra'];
        $this->respuesta = $def['respuesta'];
    }

    /** Texto que el usuario debe escribir tal cual para confirmar una acción `si_con_texto`. */
    public function confirmacionEsperada(): string
    {
        return $this->nombre;
    }
}
