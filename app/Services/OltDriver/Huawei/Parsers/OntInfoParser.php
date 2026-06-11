<?php

namespace App\Services\OltDriver\Huawei\Parsers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the output of `display ont info {frame} {slot} {port} all` (or a
 * single ont-id variant) on a Huawei MA5800/MA5600 OLT.
 *
 * Each ONT block is a series of "  Key  :  Value" lines, terminated by a
 * separator line (---) or a blank line after a complete block.
 *
 * Returns: array of [
 *   'ont_id'         => int,
 *   'sn'             => string,
 *   'run_state'      => string,   // 'online'|'offline'|...
 *   'control_flag'   => string,   // 'active'|'deactive'
 *   'config_state'   => string,   // 'normal'|'mismatch'
 *   'description'    => string,
 *   'last_up_time'   => string|null,
 *   'last_down_time' => string|null,
 *   'last_down_cause'=> string|null,
 *   'distance_m'     => int|null,
 * ]
 * Unexpected lines are silently ignored; empty output returns [].
 */
class OntInfoParser
{
    public static function parse(string $output, LoggerInterface $logger = new NullLogger()): array
    {
        $onts    = [];
        $current = [];

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            // Skip comment lines (fixture headers)
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }

            // Separator / summary line — flush current block
            if (preg_match('/^\s*-{10,}/', $line) || preg_match('/In port .* ONTs? have been displayed/i', $line)) {
                if (isset($current['sn'])) {
                    $onts[] = self::normalizeBlock($current);
                }
                $current = [];
                continue;
            }

            // Key : Value line
            if (preg_match('/^\s+([^:#\n]+?)\s*:\s*(.*)\s*$/', $line, $m)) {
                $key   = self::normalizeKey($m[1]);
                $value = trim($m[2]);
                $current[$key] = $value;
                continue;
            }

            // Blank line — flush if block has an SN
            if (trim($line) === '' && isset($current['sn'])) {
                $onts[] = self::normalizeBlock($current);
                $current = [];
                continue;
            }

            // Any other non-empty, non-key line (e.g. "No related information.")
            if (trim($line) !== '') {
                $logger->warning('[olt-huawei] OntInfoParser:unexpected-line', ['line' => $line]);
            }
        }

        // Flush any trailing block
        if (isset($current['sn'])) {
            $onts[] = self::normalizeBlock($current);
        }

        return $onts;
    }

    private static function normalizeKey(string $raw): string
    {
        // "ONT-ID" → "ont_id", "Run state" → "run_state", "ONT distance(m)" → "ont_distance_m"
        $key = strtolower(preg_replace('/[\s\-\/\(\)]+/', '_', trim($raw)));
        return rtrim($key, '_');
    }

    private static function normalizeBlock(array $raw): array
    {
        return [
            'ont_id'          => (int) ($raw['ont_id'] ?? 0),
            'sn'              => $raw['sn'] ?? '',
            'run_state'       => strtolower($raw['run_state'] ?? 'offline'),
            'control_flag'    => strtolower($raw['control_flag'] ?? ''),
            'config_state'    => strtolower($raw['config_state'] ?? ''),
            'description'     => $raw['description'] ?? '',
            'last_up_time'    => ($raw['last_up_time'] ?? '-') !== '-' ? ($raw['last_up_time'] ?? null) : null,
            'last_down_time'  => ($raw['last_down_time'] ?? '-') !== '-' ? ($raw['last_down_time'] ?? null) : null,
            'last_down_cause' => ($raw['last_down_cause'] ?? '-') !== '-' ? ($raw['last_down_cause'] ?? null) : null,
            'distance_m'      => isset($raw['ont_distance_m']) && is_numeric($raw['ont_distance_m'])
                                  ? (int) $raw['ont_distance_m']
                                  : null,
        ];
    }
}
