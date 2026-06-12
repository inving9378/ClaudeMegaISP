<?php

namespace App\Services\OltDriver\Huawei\Parsers;

/**
 * Parses the output of `display board 0` on a Huawei MA5800/MA5600 OLT.
 *
 * Returns: array of ['slot'=>int, 'board'=>string, 'status'=>string,
 *                    'port_count'=>int, 'is_gpon'=>bool]
 *
 * port_count is derived from the board model name (hardware spec), not parsed
 * from the CLI output — the board table does not include a port column.
 */
class BoardParser
{
    /**
     * GPON PON-port counts by board model (Huawei hardware spec).
     *
     * H902GPHF and H901GPHF are both 16-port GPON boards — they differ in
     * transceiver class (H902 = Class C+++ / higher power budget), not port count.
     * Confirmed empirically: port 0/2/8 (slot 2 = H902GPHF) holds 112 ONTs,
     * proving the board has at least port 8; SmartOLT shows ports 0-15 on those slots.
     * Leaving H901GPHF-8 for OLT variants that genuinely ship an 8-port variant.
     */
    private const GPON_PORT_COUNTS = [
        'H902GPHF'   => 16,
        'H901GPHF'   => 16,
        'H901GPHF-8' => 8,
    ];

    public static function parse(string $output): array
    {
        $boards = [];

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            if ($line === '' || str_starts_with(ltrim($line), '#') || str_starts_with(ltrim($line), '-')) {
                continue;
            }

            // Table row: "  1       H902GPHF   Normal   ..."
            $parts = preg_split('/\s+/', trim($line), 4);
            if (count($parts) < 2 || ! ctype_digit($parts[0])) {
                continue;
            }

            $slot      = (int) $parts[0];
            $boardName = $parts[1];
            $status    = $parts[2] ?? '';

            if (! ctype_alnum($boardName)) {
                continue;
            }

            $upper     = strtoupper($boardName);
            $isGpon    = isset(self::GPON_PORT_COUNTS[$upper]);
            $portCount = self::GPON_PORT_COUNTS[$upper] ?? 0;

            $boards[] = [
                'slot'       => $slot,
                'board'      => $boardName,
                'status'     => $status,
                'port_count' => $portCount,
                'is_gpon'    => $isGpon,
            ];
        }

        return $boards;
    }

    public static function isGponBoard(string $boardName): bool
    {
        return isset(self::GPON_PORT_COUNTS[strtoupper($boardName)]);
    }
}
