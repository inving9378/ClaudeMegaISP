<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * CARRIL AUTO/BANDEJA para candidatos de SEGURIDAD del priorizador de riesgo (#918 → wiring #9990060).
 *
 * Determinista, SIN segunda llamada a IA: evalúa el veredicto que ya escribió
 * `RevisorService::briefarSeguridad()` (subcat + brief de Opus) contra el criterio declarado en
 * `config/circuito_hardening.php`, reusando `DetectorTerminos` (única definición de "¿este texto
 * menciona X?" del circuito — ver su docblock).
 *
 * BANDEJA es el default restrictivo y gana siempre — nunca se invierte la carga de prueba (#918
 * punto 3). Solo aplica a categoria=seguridad; dinero/negocio/prod/no_aplica quedan fuera de
 * alcance (siguen su camino actual, sin carril).
 */
class CarrilSeguridad
{
    /**
     * @param array $v   Veredicto de briefarSeguridad() (categoria/subcat/texto_brief/...).
     * @param array $cfg config('circuito_hardening') completo (auto_terminos/bandeja_excepciones/
     *                    bandeja_terminos_existentes).
     * @return string|null 'auto'|'bandeja'|null (null = fuera de alcance, no es de seguridad).
     */
    public static function calcular(array $v, array $cfg): ?string
    {
        if (($v['categoria'] ?? null) !== 'seguridad') {
            return null;
        }

        $blob = mb_strtolower(($v['subcat'] ?? '') . ' ' . ($v['texto_brief'] ?? ''));

        // BANDEJA gana siempre: se evalúa primero y sobre la unión de excepciones + lista existente.
        $bandeja = array_merge((array) ($cfg['bandeja_excepciones'] ?? []), (array) ($cfg['bandeja_terminos_existentes'] ?? []));
        foreach ($bandeja as $t) {
            if (DetectorTerminos::dispara($blob, (string) $t)) {
                return 'bandeja';
            }
        }

        foreach ((array) ($cfg['auto_terminos'] ?? []) as $t) {
            if (DetectorTerminos::dispara($blob, (string) $t)) {
                return 'auto';
            }
        }

        return 'bandeja'; // default restrictivo: nada listado en auto_terminos = bandeja
    }
}
