<?php

namespace App\Services\OltDriver\Huawei\Parsers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parses the output of `display ont optical-info {port} {ont_id|all}` executed
 * from within the `interface gpon {frame}/{slot}` view on a Huawei MA5800/MA5600.
 *
 * @pending-validation — field mapping must be verified against the live OLT.
 *
 * Ground truth (SmartOLT session B1 2026-06-10, ONU 0/3/2 ont-id 0, SN HWTCFEFCC9A2):
 *   Rx ONU  = -8.54  dBm  → "Rx optical power(dBm)"           → rx_onu  → signal_1490 (downstream, 1490 nm)
 *   Rx OLT  = -13.04 dBm  → "OLT Rx ONT optical power(dBm)"   → rx_olt  → signal_1310 (upstream,   1310 nm)
 *
 * Field semantics:
 *   rx_onu  = power the ONT RECEIVES from the OLT (downstream, customer's signal)
 *   tx_onu  = power the ONT SENDS to the OLT (upstream, ONT laser output)
 *   rx_olt  = power the OLT RECEIVES from the ONT (upstream, measured at OLT)
 *
 * Returns: array of [
 *   'ont_id'      => int,
 *   'rx_onu'      => float|null,   // dBm, signal_1490
 *   'tx_onu'      => float|null,   // dBm
 *   'rx_olt'      => float|null,   // dBm, signal_1310
 *   'temperature' => int|null,     // Celsius
 *   'voltage'     => float|null,   // V (as reported by OLT, e.g. 3.380)
 *   'bias_current'=> float|null,   // mA
 *   'distance_m'  => int|null,
 * ]
 * "--" values are returned as null. Unexpected lines are silently ignored.
 */
class OpticalInfoParser
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

            // Blank line — flush block if it has data
            if (trim($line) === '' && ! empty($current)) {
                if (isset($current['ont_id']) || isset($current['rx_optical_power_dbm'])) {
                    $entries[] = self::normalizeBlock($current);
                    $current   = [];
                }
                continue;
            }

            if (trim($line) !== '') {
                $logger->warning('[olt-huawei] OpticalInfoParser:unexpected-line', ['line' => $line]);
            }
        }

        if (! empty($current)) {
            $entries[] = self::normalizeBlock($current);
        }

        return $entries;
    }

    private static function normalizeKey(string $raw): string
    {
        $key = strtolower(preg_replace('/[\s\-\/\(\)]+/', '_', trim($raw)));
        return rtrim($key, '_');
    }

    private static function toFloat(string $value): ?float
    {
        $value = trim($value);
        if ($value === '--' || $value === '-' || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        // Handle values with trailing qualifiers, e.g. "-13.47, out of range[-35.00, -15.00]".
        // Extract the leading numeric token only.
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
        return is_numeric($value) ? (int) $value : null;
    }

    private static function normalizeBlock(array $raw): array
    {
        return [
            'ont_id'       => (int) ($raw['ont_id'] ?? 0),
            // @pending-validation: verify "Rx optical power" maps to downstream (ONT Rx, 1490 nm)
            'rx_onu'       => self::toFloat($raw['rx_optical_power_dbm'] ?? '--'),
            'tx_onu'       => self::toFloat($raw['tx_optical_power_dbm'] ?? '--'),
            // @validated B1c 2026-06-12: "OLT Rx ONT optical power" = upstream (OLT Rx, 1310 nm) ✓
            'rx_olt'       => self::toFloat($raw['olt_rx_ont_optical_power_dbm'] ?? '--'),
            'temperature'  => self::toInt($raw['temperature_c'] ?? $raw['temperature'] ?? '--'),
            // Voltage reported in Volts (e.g. "3.380"); keep as float to preserve precision.
            'voltage'      => self::toFloat($raw['voltage_v'] ?? $raw['voltage'] ?? '--'),
            // VRP label is "Laser bias current(mA)" → key "laser_bias_current_ma".
            'bias_current' => self::toFloat($raw['laser_bias_current_ma'] ?? $raw['bias_current_ma'] ?? '--'),
            'distance_m'   => self::toInt($raw['distance_m'] ?? '--'),
        ];
    }
}
