<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * DETECCIÓN DE TÉRMINOS DE FRONTERA DURA — definición ÚNICA.
 *
 * Contexto (Irving, 2026-08-20): «Dos semánticas para lo mismo es cómo llegamos aquí».
 *
 * Había tres implementaciones distintas de "¿este texto menciona algo peligroso?", cada una con su
 * propio criterio, y las tres decidiendo cosas que importan:
 *
 *   · `RevisorService::triarNivelNull()` — substring para una lista, `\b…\b` para otra.
 *   · `RevisorService::enAlcance()`      — substring crudo, sin quitar boilerplate ni negaciones.
 *   · `ThomasService::categoriaFronteraDura()` — substring crudo, y es la puerta de NACIMIENTO:
 *     lo que decide si un item que Irving crea nace autorizado o no.
 *
 * El costo real: los items #874-#877 salieron nivel C por su PROPIO bloque de guardrails. El #875
 * disparó por la línea «PROHIBIDO `migrate:fresh`» — el texto que existe para PROTEGER fue el que
 * encendió la alarma. Y el mayor falso positivo medido no era ni siquiera prosa: `deploy` pegaba en
 * 50 de 163 items por la ruta `deploy/circuito/npm-build.sh` de la plantilla.
 *
 * Aquí viven las tres piezas, una sola vez:
 *   1. `limpiar()`   — quita las líneas que son PROCESO (guardrails, verificación, rutas de la
 *                      herramienta del circuito) para clasificar por el TRABAJO, no por el envoltorio.
 *   2. `apariciones()` — anclado al INICIO de palabra siempre; el único grado de libertad es si
 *                      además se ancla el final.
 *   3. `dispara()`   — 1 + 2 + la ventana de negación (#844).
 *
 * NO decide niveles ni estados: sólo dice si un término aparece de verdad. Quién actúa sobre eso
 * es cosa de cada llamador.
 */
class DetectorTerminos
{
    /**
     * Líneas de PROCESO que no describen el trabajo. Si una línea contiene uno de estos marcadores,
     * se cae entera antes de buscar términos.
     *
     * Sesgo deliberado: sólo marcadores INEQUÍVOCOS de proceso. Quitar de más aquí sólo baja un item
     * a B, donde el revisor lo mira con el texto completo — un error barato. Quitar de menos es lo
     * que mandó cuatro items a la bandeja de Irving por decir que NO tocaban producción.
     */
    public const MARCADORES_PROCESO = [
        // guardrails de entorno
        'guardrail', 'guardrail:', 'solo en dev', 'sólo en dev', 'solo dev', 'nunca prod',
        'no tocar prod', 'sin tocar prod', 'no toca prod', 'nunca tocar prod', 'jamás prod', 'jamas prod',
        'dev.meganett', 'v1megaisp', '192.168.105',
        // proceso del circuito
        'circuito pausad', 'worktree aislad', 'rama propia', 'revisar-y-mergear', 'revisar y mergear',
        'sin auto-merge', 'sin push', 'no mergear', 'checkpoint', 'reanudar el circuito', 'reanuda',
        'trabajar solo en', 'ejecución:', 'ejecucion:',
        // topes duros enumerados DENTRO del item (lo que hundió a #874/#877)
        'topes duros', 'tope duro', 'prohibido', 'no relajar', 'no se relajan', 'siguen intactos',
        'migrate:fresh', 'migrate:refresh', 'migrate:reset',
        // rutas de la herramienta del propio circuito: "deploy" ahí es un DIRECTORIO, no una acción
        'deploy/circuito', 'npm-build.sh', 'cron-wrap.sh',
    ];

    /**
     * Negaciones INEQUÍVOCAS que, cerca y antes del término, lo eximen (#844).
     * Corta y literal a propósito: una lista larga empieza a eximir lo que no debe.
     */
    public const NEGACIONES = [
        'no toca', 'no tocamos', 'no se toca', 'no se tocan', 'sin tocar',
        'no incluye', 'no incluyen', 'no se incluye', 'sin incluir',
        'no modifica', 'no modifican', 'no se modifica', 'sin modificar',
        'no afecta', 'no afectan', 'no se afecta', 'sin afectar',
        'no altera', 'no alteran', 'sin alterar',
        'no cambia', 'no cambian', 'sin cambiar',
        'no involucra', 'no requiere', 'no usa', 'no utiliza',
        // Prohibiciones: «Nunca migrate:fresh» es una PROHIBICIÓN, no una confesión.
        'nunca', 'jamás', 'jamas', 'prohibido', 'prohibida', 'prohibidas', 'prohibidos',
        'evitar', 'no ejecutar', 'no correr', 'no lanzar',
    ];

    /**
     * Ventana de negación en BYTES (no mb_*) a propósito: `preg_match_all` con offsets devuelve
     * bytes aun con el modificador /u, y mezclarlo con funciones mb_* produce cortes desalineados.
     */
    public const VENTANA_NEGACION_BYTES = 90;

    /** Quita las líneas que son proceso, no trabajo. */
    public static function limpiar(string $texto): string
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $texto) as $linea) {
            $low  = mb_strtolower($linea);
            $skip = false;
            foreach (self::MARCADORES_PROCESO as $m) {
                if (str_contains($low, $m)) {
                    $skip = true;
                    break;
                }
            }
            if (! $skip) {
                $out[] = $linea;
            }
        }

        return implode("\n", $out);
    }

    /**
     * Offsets (en bytes) de cada aparición real de $kw.
     *
     * Siempre anclado al INICIO de palabra. `$palabraCompleta` decide el final:
     *   · true  → `\bkw\b`.  Cortos/ambiguos: "rol" en "control", "prod" en "producto".
     *   · false → `\bkw\w*`. Largos e inequívocos, para seguir cazando flexiones:
     *     "factura" → "facturas"/"facturación"; "destructiv" → "destructiva".
     *
     * @return int[]
     */
    public static function apariciones(string $heno, string $kw, bool $palabraCompleta): array
    {
        if ($kw === '') {
            return [];
        }

        $patron = '/\b' . preg_quote($kw, '/') . ($palabraCompleta ? '\b' : '\w*') . '/u';

        if (! preg_match_all($patron, $heno, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(fn ($x) => (int) $x[1], $m[0]);
    }

    /**
     * ¿$kw dispara de verdad? = aparece Y no todas sus apariciones están negadas.
     *
     * Sesgo conservador (#844): basta UNA aparición sin negación cerca para que dispare. Una
     * mención ambigua no exime nada.
     */
    public static function dispara(string $heno, string $kw, bool $palabraCompleta = false): bool
    {
        $posiciones = self::apariciones($heno, $kw, $palabraCompleta);
        if ($posiciones === []) {
            return false;
        }

        foreach ($posiciones as $pos) {
            if (! self::negacionAntes($heno, $pos)) {
                return true;   // al menos una aparición limpia → dispara
            }
        }

        return false;   // todas negadas
    }

    /** ¿Hay una negación inequívoca justo antes, en la misma oración y dentro de la ventana? */
    public static function negacionAntes(string $heno, int $posKeyword): bool
    {
        $inicio   = max(0, $posKeyword - self::VENTANA_NEGACION_BYTES);
        $contexto = substr($heno, $inicio, $posKeyword - $inicio);

        // No cruzar el límite de oración: sólo lo que sigue al último terminador del contexto.
        $ultimoCorte = 0;
        foreach (['.', '!', '?', "\n\n"] as $sep) {
            $p = strrpos($contexto, $sep);
            if ($p !== false) {
                $ultimoCorte = max($ultimoCorte, $p + strlen($sep));
            }
        }
        $oracion = substr($contexto, $ultimoCorte);

        foreach (self::NEGACIONES as $neg) {
            if (strrpos($oracion, $neg) !== false) {
                return true;
            }
        }

        return false;
    }
}
