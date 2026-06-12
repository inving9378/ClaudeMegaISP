<?php

namespace App\Services\OltDriver\Huawei\Parsers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the tabular output of `display ont info {port} all` executed from
 * inside the `interface gpon {frame}/{slot}` view on a Huawei MA5800/MA5600.
 *
 * This is the BATCH listing format — one row per ONT, not the detailed
 * per-ONT block that OntInfoParser handles.
 *
 * VRP output structure (two sections):
 *
 *   Section 1 — main status table (header spans TWO lines):
 *     F/S/P   ONT         SN         Control     Run      Config   Match    Protect
 *             ID                     flag        state    state    state    side
 *     ----
 *     0/ 3/2    0  48575443FEFCC9A2  active      online   normal   mismatch no
 *
 *   Section 2 — description table:
 *     F/S/P   ONT-ID   Description
 *     [separator may or may not appear — VRP V100R018C00 SPH505 omits it]
 *     0/ 3/2       0   some description
 *
 *   Summary: "In port 0/ 3/2 , the total of ONTs are: N, online: M"
 *
 * Returns: array of [
 *   'ont_id'        => int,
 *   'sn'            => string,   // hex: "48575443FEFCC9A2"
 *   'run_state'     => string,   // 'online'|'offline'|...
 *   'control_flag'  => string,   // 'active'|'deactive'
 *   'config_state'  => string,   // 'normal'|'mismatch'|...
 *   'match_state'   => string,
 *   'description'   => string,
 * ]
 * Empty port returns [].
 */
class OntListParser
{
    // Parser states
    private const S_SCAN        = 'scan';        // looking for first header
    private const S_SKIP_H2     = 'skip_h2';     // skip second header line
    private const S_DATA        = 'data';        // reading main table rows
    private const S_DESC_HEADER = 'desc_header'; // found description header, skip separator
    private const S_DESC        = 'desc';        // reading description rows

    public static function parse(string $output, LoggerInterface $logger = new NullLogger()): array
    {
        $onts  = [];   // indexed by ont_id
        $state = self::S_SCAN;

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            // Fixture comment lines
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }

            // Failure / empty responses
            if (preg_match('/Failure:|No related information|do not exist/i', $line)) {
                return [];
            }

            // Summary — done
            if (preg_match('/the total of ONTs are/i', $line)) {
                break;
            }

            // Separator lines — ignored in all states
            if (preg_match('/^\s*-{10,}\s*$/', $line)) {
                if ($state === self::S_DESC_HEADER) {
                    $state = self::S_DESC;
                }
                continue;
            }

            switch ($state) {
                case self::S_SCAN:
                    // First header line contains "F/S/P", "SN" and "Control" (no "ONT-ID" word)
                    if (preg_match('/F\/S\/P.*SN.*Control/i', $line)) {
                        $state = self::S_SKIP_H2;
                    }
                    // Description header: "F/S/P  ONT-ID  Description"
                    elseif (preg_match('/F\/S\/P\s+ONT-ID\s+Description/i', $line)) {
                        $state = self::S_DESC_HEADER;
                    }
                    break;

                case self::S_SKIP_H2:
                    // Skip the second header row ("ID  flag  state  state  state  side")
                    // then next non-separator = separator handled above → move to S_DATA
                    // A separator line after S_SKIP_H2 sets us to data-reading state
                    $state = self::S_DATA;
                    break;

                case self::S_DATA:
                    // Description section starts when we encounter its header
                    if (preg_match('/F\/S\/P\s+ONT-ID\s+Description/i', $line)) {
                        $state = self::S_DESC_HEADER;
                        break;
                    }

                    // Data row: "  0/ 3/2    0  48575443FEFCC9A2  active  online  normal  mismatch  no"
                    if (preg_match(
                        '/^\s+\d+\/\s*\d+\/\s*\d+\s+(\d+)\s+([0-9A-Fa-f]{16})\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/',
                        $line,
                        $m
                    )) {
                        $ontId        = (int) $m[1];
                        $onts[$ontId] = [
                            'ont_id'       => $ontId,
                            'sn'           => strtoupper($m[2]),
                            'control_flag' => strtolower($m[3]),
                            'run_state'    => strtolower($m[4]),
                            'config_state' => strtolower($m[5]),
                            'match_state'  => strtolower($m[6]),
                            'description'  => '',
                        ];
                    } elseif (trim($line) !== '') {
                        $logger->debug('[olt-huawei] OntListParser:skip', ['line' => $line]);
                    }
                    break;

                case self::S_DESC_HEADER:
                    // VRP V100R018C00 SPH505 does NOT emit a separator after the
                    // description header — data rows appear immediately.
                    // Transition to S_DESC as soon as we see a data row (separator
                    // transition is still handled above for firmware variants that do emit one).
                    if (preg_match('/^\s+\d+\/\s*\d+\/\s*\d+\s+(\d+)\s+(.*?)\s*$/', $line, $m)) {
                        $state = self::S_DESC;
                        $ontId = (int) $m[1];
                        if (isset($onts[$ontId])) {
                            $onts[$ontId]['description'] = trim($m[2]);
                        }
                    }
                    break;

                case self::S_DESC:
                    // Description row: "  0/ 3/2       0   some description"
                    if (preg_match('/^\s+\d+\/\s*\d+\/\s*\d+\s+(\d+)\s+(.*?)\s*$/', $line, $m)) {
                        $ontId = (int) $m[1];
                        if (isset($onts[$ontId])) {
                            $onts[$ontId]['description'] = trim($m[2]);
                        }
                    }
                    break;
            }
        }

        return array_values($onts);
    }
}
