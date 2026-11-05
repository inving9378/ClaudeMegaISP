<?php

namespace App\Support;

/**
 * CRM-E 03 (#557): reglas de nombre del prospecto según el tipo de persona. Con persona moral el
 * campo «name» es la razón social (letras, dígitos, espacios y . , & -) y no exige apellidos; en
 * cualquier otro caso (incluido sin tipo = residencial) rigen las reglas de siempre.
 */
class PersonaMoralCrm
{
    public const REGEX_RAZON_SOCIAL = '/^[\p{L}\p{N}\s.,&\-]+$/u';
    public const REGEX_PERSONA_FISICA = '/^[a-zA-ZÁÉÍÓÚáéíóúÑñ\s]+$/u';

    public static function esMoral($request): bool
    {
        return $request->input('tipo_persona') === 'moral';
    }

    public static function regexNombre($request): string
    {
        return self::esMoral($request) ? self::REGEX_RAZON_SOCIAL : self::REGEX_PERSONA_FISICA;
    }

    /** Apellidos: obligatorios salvo persona moral. */
    public static function reglaApellido($request): string
    {
        return self::esMoral($request) ? 'nullable' : 'required';
    }

    /** RFC en mayúsculas y sin espacios, guiones ni puntos, para comparar duplicados. */
    public static function normalizarRfc(?string $rfc): string
    {
        return strtoupper(preg_replace('/[\s.\-]+/', '', (string) $rfc));
    }
}
