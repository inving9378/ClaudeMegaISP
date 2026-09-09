<?php

namespace App\Support;

/**
 * Rol de esta instalación de MegaISP: 'operador' (Meganet) o 'cliente' (arrendada).
 *
 * Punto ÚNICO de lectura. Nadie llama a `config('instancia.rol')` ni a
 * `env('INSTANCE_ROLE')` por su cuenta: si el criterio de "qué cuenta como
 * operador" se decide en varios lugares, tarde o temprano uno de ellos queda
 * más permisivo que el resto, y el que decide mal es el que abre la puerta.
 */
class RolInstancia
{
    public const OPERADOR = 'operador';
    public const CLIENTE  = 'cliente';

    /**
     * Rol vigente. Un valor ausente, vacío o no reconocido cae a 'cliente':
     * ante la duda, la instalación es la restringida (ver config/instancia.php).
     */
    public static function actual(): string
    {
        return self::normalizar(config('instancia.rol'), config('instancia.roles_validos'));
    }

    /**
     * Normaliza un valor crudo a un rol válido. FUNCIÓN PURA: no lee config ni
     * entorno, así que la regla del default restrictivo se puede probar sin
     * levantar Laravel — que es justo lo que hay que poder verificar barato y a
     * menudo, porque es la que sostiene el blindaje entero.
     */
    public static function normalizar($valor, ?array $validos = null): string
    {
        $rol     = strtolower(trim((string) $valor));
        $validos = $validos ?: [self::OPERADOR, self::CLIENTE];

        return in_array($rol, $validos, true) ? $rol : self::CLIENTE;
    }

    public static function esOperador(): bool
    {
        return self::actual() === self::OPERADOR;
    }

    /**
     * ¿Esta instalación satisface el rol que EXIGE un módulo?
     *
     * Un módulo que no declara `instance_role` no exige nada y corre en
     * cualquier instalación — que es el caso de los 48 módulos actuales, así
     * que agregar este mecanismo no cambia el comportamiento de ninguno.
     */
    public static function satisface(?string $rolExigido): bool
    {
        return self::satisfaceCon(self::actual(), $rolExigido);
    }

    /** Misma regla que `satisface()`, pero pura: el rol actual se pasa como dato. */
    public static function satisfaceCon(string $rolActual, ?string $rolExigido): bool
    {
        if ($rolExigido === null || trim($rolExigido) === '') {
            return true;
        }

        return $rolActual === strtolower(trim($rolExigido));
    }

    /**
     * Mensaje para el operador cuando un módulo no puede activarse aquí.
     *
     * `$rolActual` se puede inyectar para poder probar el mensaje sin levantar
     * Laravel; en uso normal se omite y se resuelve solo.
     */
    public static function mensajeRechazo(string $slug, string $rolExigido, ?string $rolActual = null): string
    {
        $rolActual ??= self::actual();

        return "El módulo '{$slug}' solo puede activarse en una instalación de tipo "
             . "'{$rolExigido}', y esta es de tipo '{$rolActual}'. "
             . 'Si esta instalación sí es la de Meganet, define INSTANCE_ROLE=operador '
             . 'en su archivo .env y vuelve a intentarlo.';
    }
}
