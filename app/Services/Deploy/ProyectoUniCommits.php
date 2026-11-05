<?php

namespace App\Services\Deploy;

/**
 * Detector puro (sin Laravel) de commits del proyecto de unificación (UNI-T).
 * Un commit pertenece al proyecto si su mensaje trae una línea `Proyecto-UNI: #<id>`.
 */
class ProyectoUniCommits
{
    public const PATRON = '/^Proyecto-UNI:\s*#?\d+/mi';

    /**
     * @param  string[]  $mensajes  mensajes completos de commit
     * @return int  cuántos pertenecen al proyecto
     */
    public static function contar(array $mensajes): int
    {
        $n = 0;
        foreach ($mensajes as $mensaje) {
            if (preg_match(self::PATRON, (string) $mensaje)) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * Mensajes completos entre $desde (tag, null = toda la historia) y $hasta, vía git.
     *
     * @return string[]
     */
    public static function mensajesEnRango(string $repo, ?string $desde, string $hasta = 'HEAD'): array
    {
        $rango = $desde ? escapeshellarg("{$desde}..{$hasta}") : escapeshellarg($hasta);
        $out   = shell_exec('git -C ' . escapeshellarg($repo) . " log --no-merges --format=%B%x00 {$rango} 2>/dev/null");

        return array_values(array_filter(explode("\0", (string) $out), fn ($m) => trim($m) !== ''));
    }

    /** Último tag alcanzable desde $hasta (null si no hay). */
    public static function ultimoTag(string $repo, string $hasta = 'HEAD'): ?string
    {
        $tag = trim((string) shell_exec('git -C ' . escapeshellarg($repo) . ' describe --tags --abbrev=0 ' . escapeshellarg($hasta) . ' 2>/dev/null'));
        return $tag !== '' ? $tag : null;
    }
}
