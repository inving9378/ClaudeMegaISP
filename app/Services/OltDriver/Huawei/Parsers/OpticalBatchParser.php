<?php

namespace App\Services\OltDriver\Huawei\Parsers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the tabular output of `display ont optical-info {port} all`
 * executed from inside the `interface gpon {frame}/{slot}` view.
 *
 * VRP output structure (header spans TWO lines):
 *
 *   ONT  Rx power  Tx power  OLT Rx ONT  Temperature  Voltage  Current  Distance
 *   ID   (dBm)     (dBm)     power(dBm)  (C)          (V)      (mA)     (m)
 *   ----
 *     0  -8.42     2.32      -13.44      45           3.380    15       39
 *
 * Values reported as '--' (laser off / ONU offline) are returned as null.
 *
 * Returns: array of [
 *   'ont_id'       => int,
 *   'rx_onu'       => float|null,   // Rx power at ONU (dBm)
 *   'tx_onu'       => float|null,   // Tx power at ONU (dBm)
 *   'rx_olt'       => float|null,   // OLT Rx ONT power (dBm)
 *   'temperature'  => float|null,   // Laser temperature (°C)
 *   'voltage'      => float|null,   // Supply voltage (V)
 *   'bias_current' => float|null,   // Laser bias current (mA)
 *   'distance'     => int|null,     // Fiber distance (m)
 * ]
 */
class OpticalBatchParser
{
    private const S_SCAN    = 'scan';
    private const S_SKIP_H2 = 'skip_h2';
    private const S_DATA    = 'data';

    public static function parse(string $output, LoggerInterface $logger = new NullLogger()): array
    {
        $onts  = [];
        $state = self::S_SCAN;

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (preg_match('/Failure:|No related information|do not exist/i', $line)) {
                return [];
            }

            // Separator lines
            if (preg_match('/^\s*-{10,}\s*$/', $line)) {
                continue;
            }

            switch ($state) {
                case self::S_SCAN:
                    // First header line: "ONT  Rx power  Tx power  OLT Rx ONT  Temperature  Voltage..."
                    if (preg_match('/\bONT\b.*\bRx\s+power\b.*\bTemperature\b/i', $line)) {
                        $state = self::S_SKIP_H2;
                    }
                    break;

                case self::S_SKIP_H2:
                    // Skip the second header row ("ID  (dBm)  (dBm)  power(dBm)  (C)  (V)  (mA)  (m)")
                    $state = self::S_DATA;
                    break;

                case self::S_DATA:
                    // Data row: "    0  -8.42  2.32  -13.44  45  3.380  15  39"
                    // Values can be '--' when the laser is off
                    if (preg_match(
                        '/^\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/',
                        $line,
                        $m
                    )) {
                        $onts[] = [
                            'ont_id'       => (int) $m[1],
                            'rx_onu'       => self::toFloat($m[2]),
                            'tx_onu'       => self::toFloat($m[3]),
                            'rx_olt'       => self::toFloat($m[4]),
                            'temperature'  => self::toFloat($m[5]),
                            'voltage'      => self::toFloat($m[6]),
                            'bias_current' => self::toFloat($m[7]),
                            'distance'     => self::toInt($m[8]),
                        ];
                    } elseif (trim($line) !== '') {
                        $logger->debug('[olt-huawei] OpticalBatchParser:skip', ['line' => $line]);
                    }
                    break;
            }
        }

        return $onts;
    }

    private static function toFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '--' || $value === '-' || $value === '') {
            return null;
        }
        // Extract leading numeric token (handles VRP suffixes like ", out of range[…]")
        if (preg_match('/^([-+]?\d+(?:\.\d+)?)/', $value, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    private static function toInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '--' || $value === '-' || $value === '') {
            return null;
        }
        if (preg_match('/^(\d+)/', $value, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
