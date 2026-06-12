<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use PHPUnit\Framework\TestCase;

/**
 * Validated in sesión B1c (2026-06-12) against MA5800-X7 ONU 0/3/2 SN HWTCFEFCC9A2:
 *   Rx ONU = -8.46 dBm  (expected ≈ -8.54, delta = 0.08 — within ±1.5 dBm tolerance)
 *   Rx OLT = -13.47 dBm (expected ≈ -13.04, delta = 0.43 — within ±1.5 dBm tolerance)
 */
class OpticalInfoParserTest extends TestCase
{
    private static function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_optical_info.txt');
    }

    public function test_parses_one_entry_from_fixture(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertCount(1, $entries);
    }

    public function test_ont_id_zero(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertSame(0, $entries[0]['ont_id']);
    }

    /**
     * Ground truth: Rx ONU (downstream, 1490 nm) = -8.54 dBm
     * Maps to signal_1490 in the driver shape.
     * @pending-validation
     */
    public function test_rx_onu_matches_ground_truth(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertEqualsWithDelta(-8.54, $entries[0]['rx_onu'], 0.001);
    }

    /**
     * Ground truth: Rx OLT (upstream, 1310 nm) = -13.04 dBm
     * Maps to signal_1310 in the driver shape.
     * @pending-validation
     */
    public function test_rx_olt_matches_ground_truth(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertEqualsWithDelta(-13.04, $entries[0]['rx_olt'], 0.001);
    }

    public function test_tx_onu_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertNotNull($entries[0]['tx_onu']);
        $this->assertIsFloat($entries[0]['tx_onu']);
    }

    public function test_temperature_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertSame(52, $entries[0]['temperature']);
    }

    public function test_voltage_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        // voltage is now float (Volts as reported by OLT); synthetic fixture has "3299"
        $this->assertEqualsWithDelta(3299.0, $entries[0]['voltage'], 0.001);
    }

    public function test_distance_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $this->assertSame(1143, $entries[0]['distance_m']);
    }

    public function test_dash_values_return_null(): void
    {
        $output = "  ONT-ID                                    : 0\n"
                . "  Rx optical power(dBm)                     : --\n"
                . "  Tx optical power(dBm)                     : --\n"
                . "  OLT Rx ONT optical power(dBm)             : --\n";
        $entries = OpticalInfoParser::parse($output);
        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]['rx_onu']);
        $this->assertNull($entries[0]['rx_olt']);
    }

    public function test_rx_olt_with_out_of_range_suffix_parsed(): void
    {
        // VRP appends ", out of range[lo, hi]" when value is outside thresholds.
        // The parser must extract only the leading numeric token.
        $output = "  ONT-ID : 0\n"
                . "  OLT Rx ONT optical power(dBm) : -13.47, out of range[-35.00, -15.00]\n"
                . "\n";
        $entries = OpticalInfoParser::parse($output);
        $this->assertCount(1, $entries);
        $this->assertEqualsWithDelta(-13.47, $entries[0]['rx_olt'], 0.001);
    }

    public function test_empty_output_returns_empty_array(): void
    {
        $this->assertSame([], OpticalInfoParser::parse(''));
    }

    public function test_no_related_information_returns_empty_array(): void
    {
        $this->assertSame([], OpticalInfoParser::parse('  No related information.'));
    }

    public function test_all_entries_have_required_keys(): void
    {
        $entries = OpticalInfoParser::parse(self::fixture());
        $required = ['ont_id', 'rx_onu', 'tx_onu', 'rx_olt', 'temperature', 'voltage', 'bias_current', 'distance_m'];
        foreach ($entries as $entry) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $entry, "Missing key: {$key}");
            }
        }
    }

    // ── Real fixture (B1c 2026-06-12, MA5800-X7, ONU 0/3/2 SN HWTCFEFCC9A2) ──

    private static function realFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_optical_info_real.txt');
    }

    public function test_real_rx_onu_within_tolerance(): void
    {
        $entries = OpticalInfoParser::parse(self::realFixture());
        $this->assertCount(1, $entries);
        // Ground truth ≈ -8.54; real reading = -8.46; tolerance ±1.5 dBm
        $this->assertEqualsWithDelta(-8.54, $entries[0]['rx_onu'], 1.5);
        $this->assertEqualsWithDelta(-8.46, $entries[0]['rx_onu'], 0.01);
    }

    public function test_real_rx_olt_parsed_and_within_tolerance(): void
    {
        $entries = OpticalInfoParser::parse(self::realFixture());
        // Line: "OLT Rx ONT optical power(dBm) : -13.47, out of range[-35.00, -15.00]"
        // Ground truth ≈ -13.04; real reading = -13.47; tolerance ±1.5 dBm
        $this->assertNotNull($entries[0]['rx_olt']);
        $this->assertEqualsWithDelta(-13.04, $entries[0]['rx_olt'], 1.5);
        $this->assertEqualsWithDelta(-13.47, $entries[0]['rx_olt'], 0.01);
    }

    public function test_real_bias_current_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::realFixture());
        // Line: "Laser bias current(mA) : 15" — key normalizes to "laser_bias_current_ma"
        $this->assertEqualsWithDelta(15.0, $entries[0]['bias_current'], 0.01);
    }

    public function test_real_voltage_parsed_as_float(): void
    {
        $entries = OpticalInfoParser::parse(self::realFixture());
        // Line: "Voltage(V) : 3.380" — must return float, not truncated int
        $this->assertEqualsWithDelta(3.380, $entries[0]['voltage'], 0.001);
    }

    public function test_real_temperature_parsed(): void
    {
        $entries = OpticalInfoParser::parse(self::realFixture());
        $this->assertSame(45, $entries[0]['temperature']);
    }
}
