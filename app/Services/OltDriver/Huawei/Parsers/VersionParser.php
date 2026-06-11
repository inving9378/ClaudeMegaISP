<?php

namespace App\Services\OltDriver\Huawei\Parsers;

/**
 * Parses the output of `display version` on a Huawei MA5800/MA5600 OLT.
 *
 * Returns: ['model'=>string, 'firmware'=>string, 'patch'=>string, 'uptime'=>string]
 * All fields are empty strings when not found.
 */
class VersionParser
{
    public static function parse(string $output): array
    {
        $result = ['model' => '', 'firmware' => '', 'patch' => '', 'uptime' => ''];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // "MA5800-X7 uptime is 0 week(s), 3 day(s), 14 hour(s), 22 minute(s)"
            if (preg_match('/^([\w][\w\-]*)\s+uptime is\s+(.+)/i', $line, $m)) {
                $result['model']  = $m[1];
                $result['uptime'] = trim($m[2]);
                continue;
            }

            // "  VERSION  : MA5800-X7V100R018C00SPC250"
            if (preg_match('/VERSION\s*:\s*(.+)/i', $line, $m)) {
                $raw = trim($m[1]);
                // Strip leading model prefix (e.g. "MA5800-X7V100R018..." → "V100R018...")
                if (preg_match('/(V\d+R\d+\S*)/i', $raw, $fm)) {
                    $result['firmware'] = $fm[1];
                } else {
                    $result['firmware'] = $raw;
                }
                continue;
            }

            // "  PATCH    : V100R018SPH505"  or  "  PATCH    : SPH505"
            if (preg_match('/PATCH\s*:\s*(.+)/i', $line, $m)) {
                $raw = trim($m[1]);
                if (preg_match('/(SPH\d+)/i', $raw, $pm)) {
                    $result['patch'] = $pm[1];
                } else {
                    $result['patch'] = $raw;
                }
                continue;
            }
        }

        return $result;
    }
}
