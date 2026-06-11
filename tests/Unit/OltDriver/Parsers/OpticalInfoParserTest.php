<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use PHPUnit\Framework\TestCase;

/**
 * @pending-validation — valores de señal validados contra ground truth SmartOLT (sesión B1):
 *   ONU 0/3/2 ont-id 0, SN HWTCFEFCC9A2 → Rx ONU = -8.54 dBm / Rx OLT = -13.04 dBm
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
        $this->assertSame(3299, $entries[0]['voltage']);
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
}
