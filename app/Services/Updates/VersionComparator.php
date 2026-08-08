<?php

namespace App\Services\Updates;

use Carbon\Carbon;

/**
 * Compara tags de versión estilo "V{major}.{minor}[-{d.m.Y}]" (convención de
 * ReleaseController::nextVersion()). Clave PRIMARIA: major/minor como enteros.
 * La fecha embebida en el tag solo se usa como DESEMPATE (parseada de verdad con
 * Carbon, nunca comparada como texto) — footgun documentado: "04.08.2026" <
 * "09.07.2026" como string, aunque el 4 de agosto es cronológicamente posterior.
 */
class VersionComparator
{
    /** True si $candidateTag es una versión más nueva que $currentTag. */
    public static function isNewer(string $candidateTag, ?string $currentTag): bool
    {
        if ($currentTag === null || trim($currentTag) === '') {
            return true; // sin versión instalada registrada: siempre hay actualización
        }

        if ($candidateTag === $currentTag) {
            return false;
        }

        $candidate = self::parse($candidateTag);
        $current   = self::parse($currentTag);

        if ($candidate['major'] !== null && $current['major'] !== null) {
            if ($candidate['major'] !== $current['major']) {
                return $candidate['major'] > $current['major'];
            }
            if ($candidate['minor'] !== $current['minor']) {
                return $candidate['minor'] > $current['minor'];
            }

            // Mismo major.minor: desempata por fecha (si ambas se pudieron parsear).
            if ($candidate['date'] && $current['date'] && !$candidate['date']->equalTo($current['date'])) {
                return $candidate['date']->gt($current['date']);
            }

            // major.minor idénticos y sin fecha confiable para desempatar: tags
            // distintos pero sin forma de ordenar → conservador, no se ofrece update.
            return false;
        }

        // No se pudo parsear el número de versión de alguno de los dos tags:
        // única señal disponible es la fecha (si ambas existen).
        if ($candidate['date'] && $current['date']) {
            return $candidate['date']->gt($current['date']);
        }

        // Nada parseable en ninguno de los dos: tags distintos sin forma confiable
        // de ordenar → se avisa (mejor un falso positivo que quedarse callado).
        return true;
    }

    /**
     * @return array{major: ?int, minor: ?int, date: ?Carbon}
     */
    public static function parse(string $tag): array
    {
        $tag = trim($tag);

        $major = null;
        $minor = null;
        if (preg_match('/^v?(\d+)\.(\d+)/i', $tag, $m)) {
            $major = (int) $m[1];
            $minor = (int) $m[2];
        }

        return [
            'major' => $major,
            'minor' => $minor,
            'date'  => self::extractDate($tag),
        ];
    }

    /** Extrae y parsea de verdad (nunca como string) la fecha DD.MM.YYYY embebida en el tag. */
    private static function extractDate(string $tag): ?Carbon
    {
        if (!preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $tag, $m)) {
            return null;
        }

        [$full, $d, $mo, $y] = $m;

        try {
            $parsed = Carbon::createFromFormat('!d.m.Y', "{$d}.{$mo}.{$y}");
        } catch (\Throwable $e) {
            return null;
        }

        // createFromFormat es permisivo (ej. "32.13.2026" se normaliza en vez de
        // fallar): round-trip contra el texto original para descartar fechas inválidas.
        if (!$parsed || $parsed->format('d.m.Y') !== "{$d}.{$mo}.{$y}") {
            return null;
        }

        return $parsed;
    }
}
