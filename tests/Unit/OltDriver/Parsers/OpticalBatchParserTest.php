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

    // ── Real fixture B3a — port 0/2/8 (112 ONTs, 80 online with optical data) ─
    // Only online ONTs have optical readings; offline ONTs are absent from this output.

    public function test_b3a_count_equals_online_onu_count(): void
    {
        // VRP only returns optical rows for online ONTs.
        // The ont info fixture confirms 80 online → optical must also yield 80.
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        $this->assertCount(80, $result, 'Optical parser must return exactly 80 rows (one per online ONT)');
    }

    public function test_b3a_all_ont_ids_are_integers(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        foreach ($result as $item) {
            $this->assertIsInt($item['ont_id'], "ont_id must be int, got: " . gettype($item['ont_id']));
        }
    }

    public function test_b3a_rx_onu_values_are_floats_or_null(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        foreach ($result as $item) {
            $this->assertTrue(
                is_float($item['rx_onu']) || is_null($item['rx_onu']),
                "rx_onu must be float or null for ONT {$item['ont_id']}"
            );
        }
    }

    public function test_b3a_rx_onu_range_is_plausible(): void
    {
        $result  = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        $values  = array_filter(array_column($result, 'rx_onu'), fn($v) => $v !== null);
        foreach ($values as $v) {
            // Normal ONT Rx range: -8 dBm (sobrepotencia) to -32 dBm (límite alcance)
            $this->assertGreaterThan(-35.0, $v, "rx_onu {$v} is below plausible range");
            $this->assertLessThan(5.0,      $v, "rx_onu {$v} is above plausible range");
        }
    }

    public function test_b3a_ont_ids_include_known_offline_gaps(): void
    {
        // ONT IDs in optical output are NOT 0-111 sequential — offline ONTs are missing.
        $result  = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        $ids     = array_column($result, 'ont_id');
        $allIds  = range(0, 111);

        // There must be gaps (the 32 offline ONTs are absent)
        $this->assertNotEquals($allIds, $ids, 'Optical output should have gaps for offline ONTs');
        $this->assertCount(80, $ids, 'Must have exactly 80 ont_ids');
    }

    public function test_b3a_distance_values_are_int_or_null(): void
    {
        $result = OpticalBatchParser::parse(self::fixture('display_ont_optical_batch_0_2_8_real.txt'));
        foreach ($result as $item) {
            $this->assertTrue(
                is_int($item['distance']) || is_null($item['distance']),
                "distance must be int or null for ONT {$item['ont_id']}"
            );
        }
    }
}
