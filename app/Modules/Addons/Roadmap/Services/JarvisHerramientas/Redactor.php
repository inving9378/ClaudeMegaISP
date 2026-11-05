<?php

namespace App\Modules\Addons\Roadmap\Services\JarvisHerramientas;

/**
 * #1106 — frontera de datos de Jarvis. TODA salida de herramienta (incluido el texto libre de
 * items y comentarios) pasa por aquí antes de llegar al modelo: enmascara patrones de secreto
 * (llaves de API, tokens, Bearer, password=…, CLABE/tarjeta) y datos de contacto sueltos.
 */
final class Redactor
{
    public const MASCARA = '[oculto]';

    private const PATRONES = [
        '/\bBearer\s+[A-Za-z0-9._~+\/=-]{8,}/i',
        '/\b(?:sk|pk|rk|ghp|gho|ghs|xox[a-z]|AKIA|AIza|ya29|eyJ)[-_A-Za-z0-9.]{8,}/',
        '/\b(?:password|passwd|pwd|contrase(?:ñ|n)a|secret|token|api[_-]?key|apikey|authorization)\s*[:=]\s*\S+/iu',
        '/\b\d{18}\b/',                                  // CLABE
        '/\b(?:\d[ -]?){13,19}\b/',                      // tarjeta
        '/\b(?=[A-Za-z0-9_]*\d)(?=[A-Za-z0-9_]*[A-Za-z])[A-Za-z0-9_]{32,}\b/',   // token largo (las ramas llevan guiones; los commits se acortan)
        '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',   // correo
        '/(?<!\d)(?:\+?52\s?)?\d{2}[ -]?\d{4}[ -]?\d{4}(?!\d)/', // teléfono
    ];

    public static function texto(string $texto): string
    {
        foreach (self::PATRONES as $p) {
            $texto = preg_replace($p, self::MASCARA, $texto) ?? $texto;
        }

        return $texto;
    }

    /** Recorre un arreglo y redacta cada cadena. */
    public static function datos(mixed $v): mixed
    {
        if (is_string($v)) {
            return self::texto($v);
        }
        if (is_array($v)) {
            return array_map([self::class, 'datos'], $v);
        }

        return $v;
    }
}
