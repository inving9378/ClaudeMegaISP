<?php

namespace App\Services\Ipv6;

/**
 * Deriva la "familia de driver" (arquitectura) de un MikroTik a partir de los
 * datos de /system resource print. Prioridad: override manual del operador >
 * architecture-name nativo de RouterOS (más confiable) > heurística por
 * prefijo de board-name (fallback para cuando ese campo falta). La tabla de
 * board-name es best-effort sobre líneas de producto públicas de MikroTik,
 * no un mapeo garantizado — por eso el override existe y siempre gana.
 */
class MikrotikBoardFamilyResolver
{
    /** @var array<string, string> prefijo de board-name => familia */
    private const BOARD_NAME_PREFIXES = [
        'CCR2' => 'arm64',
        'CCR1' => 'tile',
        'CHR' => 'x86',
        'CRS3' => 'arm',
        'CRS2' => 'mipsbe',
        'CRS1' => 'mipsbe',
        'RB1100' => 'ppc',
        'RB4' => 'arm',
        'RB5' => 'mipsbe',
        'RB9' => 'mipsbe',
        'hAP' => 'mipsbe',
        'hEX' => 'mipsbe',
        'wAP' => 'mipsbe',
        'x86' => 'x86',
    ];

    /**
     * @return array{family: string, source: string}
     */
    public static function resolve(?string $boardName, ?string $architectureName, ?string $override = null): array
    {
        if ($override !== null && trim($override) !== '') {
            return ['family' => trim($override), 'source' => 'override'];
        }

        if ($architectureName !== null && trim($architectureName) !== '') {
            return ['family' => trim($architectureName), 'source' => 'architecture-name'];
        }

        if ($boardName !== null) {
            foreach (self::BOARD_NAME_PREFIXES as $prefix => $family) {
                if (stripos($boardName, $prefix) === 0) {
                    return ['family' => $family, 'source' => 'board-name-heuristico'];
                }
            }
        }

        return ['family' => 'desconocida', 'source' => 'ninguna'];
    }
}
