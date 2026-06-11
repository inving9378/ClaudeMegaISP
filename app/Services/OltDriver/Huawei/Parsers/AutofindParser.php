<?php

namespace App\Services\OltDriver\Huawei\Parsers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the output of `display ont autofind all` on a Huawei MA5800/MA5600 OLT.
 *
 * Returns: array of [
 *   'sn'           => string,
 *   'port'         => string,   // "frame/slot/port"
 *   'vender_id'    => string,
 *   'equipment_id' => string,
 *   'autofind_time'=> string|null,
 * ]
 * Returns [] when output is "No related information." or empty.
 */
class AutofindParser
{
    public static function parse(string $output, LoggerInterface $logger = new NullLogger()): array
    {
        $entries = [];
        $current = [];

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/No related information/i', $line)) {
                return [];
            }

            // Key : Value line
            if (preg_match('/^\s+([^:#\n]+?)\s*:\s*(.*)\s*$/', $line, $m)) {
                $key   = self::normalizeKey($m[1]);
                $value = trim($m[2]);
                $current[$key] = $value;
                continue;
            }

            // Blank line — flush block
            if (trim($line) === '' && isset($current['ont_sn'])) {
                $entries[] = self::normalizeBlock($current);
                $current   = [];
                continue;
            }

            if (trim($line) !== '') {
                $logger->warning('[olt-huawei] AutofindParser:unexpected-line', ['line' => $line]);
            }
        }

        if (isset($current['ont_sn'])) {
            $entries[] = self::normalizeBlock($current);
        }

        return $entries;
    }

    private static function normalizeKey(string $raw): string
    {
        $key = strtolower(preg_replace('/[\s\-\/\(\)]+/', '_', trim($raw)));
        return rtrim($key, '_');
    }

    private static function normalizeBlock(array $raw): array
    {
        return [
            'sn'            => $raw['ont_sn'] ?? '',
            'port'          => $raw['f_s_p'] ?? '',
            'vender_id'     => $raw['venderid'] ?? '',
            'equipment_id'  => $raw['ont_equipmentid'] ?? '',
            'autofind_time' => ($raw['ont_autofind_time'] ?? '') ?: null,
        ];
    }
}
