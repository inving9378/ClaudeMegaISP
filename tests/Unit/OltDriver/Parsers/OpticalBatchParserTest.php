<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\OpticalBatchParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OpticalBatchParser — tabular `display ont optical-info {port} all`.
 *
 * Real VRP header spans TWO lines:
 *   ONT  Rx power  Tx power  OLT Rx ONT  Temperature  Voltage  Current  Distance
 *   ID   (dBm)     (dBm)     power(dBm)  (C)          (V)      (mA)     (m)
 *
 * Fixture: tests/Unit/OltDriver/fixtures/huawei/display_ont_optical_batch_real.txt
 * Ground truth (MA5800-X7, 2026-06-12, ONU 0/3/2 ont-id 0):
 *   Rx ONU  = -8.42 dBm  (within ±1.5 dBm of -8.54 reference)
 *   Rx OLT  = -13.44 dBm (within ±1.5 dBm of -13.04 reference)
 *   Voltage = 3.380 V
 *   Current = 15 mA
 *   Dist    = 39 m
 */
class OpticalBatchParserTest extends TestCase
{
    private static function fixture(string $name): string
    {
        $path = __DIR__ . '/../fixtures/huawei/' . $name;
        self::assertFileExists($path, "Fixture {$name} not found");
        return file_get_contents($path);
    }

    // ── Empty / error cases ────────────────────────────────────────────────────

    public function test_empty_output_returns_empty_array(): void
    {
        $this->assertSame([], OpticalBatchParser::parse(''));
    }

    public function test_failure_message_returns_empty_array(): void
    {
        $this->assertSame([], OpticalBatchParser::parse('Failure: No related information'));
    }

    // ── Synthetic (offline laser → '--') ──────────────────────────────────────

    public function test_dash_dash_value_returns_null(): void
    {
        $output = "  ONT  Rx power  Tx power  OLT Rx ONT  Temperature  Voltage  Current  Distance\n"
                . "  ID   (dBm)     (dBm)     power(dBm)  (C)          (V)      (mA)     (m)\n"
                . "  -------\n"
                . "    3  --        --        --          --           --       --       --\n";

        $result = OpticalBatchParser::parse($output);

        $this->assertCount(1, $result);
        $ont = $result[0];
        $this->assertSame(3,    $ont['ont_id']);
        $this->assertNull($ont['rx_onu']);
        $this->assertNull($ont['rx_olt']);
        $this->assertNull($ont['voltage']);
        $this->assertNull($ont['distance']);
    }

    public function test_multiple_onts_parsed(): void
    {
        $output = "  ONT  Rx power  Tx power  OLT Rx ONT  Temperature  Voltage  Current  Distance\n"
                . "  ID   (dBm)     (dBm)     power(dBm)  (C)          (V)      (mA)     (m)\n"
                . "  -------\n"
                . "    0  -8.50     2.30      -13.00      44           3.380    15       39\n"
                . "    1  -9.10     2.25      -14.20      46           3.370    14       85\n"
                . "    2  --        --        --          --           --       --       --\n";

        $result = OpticalBatchParser::parse($output);

        $this->assertCount(3, $result);
        $this->assertSame(0, $result[0]['ont_id']);
        $this->assertSame(1, $result[1]['ont_id']);
        $this->assertSame(2, $result[2]['ont_id']);
        $this->assertEqualsWithDelta(-9.10, $result[1]['rx_onu'], 0.001);
        $this->assertNull($result[2]['rx_onu']);
    }

    // ── Real fixture ──────────────────────────────────────────────────────────

    public function test_real_fixture_parses_one_ont(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $this->assertCount(1, $result);
    }

    public function test_real_fixture_ont_id_zero(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $this->assertSame(0, $result[0]['ont_id']);
    }

    public function test_real_rx_onu_within_tolerance(): void
    {
        // Reference: -8.54 dBm (from NMS). Tolerance: ±1.5 dBm.
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $rxOnu = $result[0]['rx_onu'];
        $this->assertNotNull($rxOnu, 'rx_onu must not be null');
        $this->assertEqualsWithDelta(-8.54, $rxOnu, 1.5, "Rx ONU {$rxOnu} dBm outside ±1.5 of -8.54");
    }

    public function test_real_rx_olt_within_tolerance(): void
    {
        // Reference: -13.04 dBm (from NMS). Tolerance: ±1.5 dBm.
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $rxOlt = $result[0]['rx_olt'];
        $this->assertNotNull($rxOlt, 'rx_olt must not be null');
        $this->assertEqualsWithDelta(-13.04, $rxOlt, 1.5, "Rx OLT {$rxOlt} dBm outside ±1.5 of -13.04");
    }

    public function test_real_voltage_is_float(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $this->assertEqualsWithDelta(3.380, $result[0]['voltage'], 0.001);
    }

    public function test_real_bias_current_parsed(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $this->assertEqualsWithDelta(15.0, $result[0]['bias_current'], 0.5);
    }

    public function test_real_distance_39m(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $this->assertSame(39, $result[0]['distance']);
    }

    public function test_result_has_expected_keys(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_real.txt'));

        $keys = ['ont_id', 'rx_onu', 'tx_onu', 'rx_olt', 'temperature', 'voltage', 'bias_current', 'distance'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result[0], "Missing key: {$key}");
        }
    }
}
