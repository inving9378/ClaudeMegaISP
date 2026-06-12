<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\OntListParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OntListParser — tabular `display ont info {port} all` format.
 *
 * The real VRP header spans TWO lines:
 *   F/S/P   ONT         SN         Control     Run      Config   Match    Protect
 *           ID                     flag        state    state    state    side
 *
 * Fixtures: tests/Unit/OltDriver/fixtures/huawei/display_ont_info_port_real.txt
 */
class OntListParserTest extends TestCase
{
    private static function fixture(string $name): string
    {
        $path = __DIR__ . '/../fixtures/huawei/' . $name;
        self::assertFileExists($path, "Fixture {$name} not found");
        return file_get_contents($path);
    }

    // ── Synthetic fixtures ─────────────────────────────────────────────────────

    private static function syntheticOutput(int $count = 2): string
    {
        $rows = '';
        $descs = '';
        for ($i = 0; $i < $count; $i++) {
            $sn    = sprintf('4857544300%06X', $i);
            $state = $i % 2 === 0 ? 'online' : 'offline';
            $rows  .= "  0/ 3/2   {$i}  {$sn}  active      {$state}   normal   match    no \n";
            $descs .= "  0/ 3/2      {$i}   ONT_{$i}\n";
        }

        $sep = "  -----------------------------------------------------------------------------\n";
        return
            "  Command:\n"
            . "            display ont info 2 all\n"
            . $sep
            . "  F/S/P   ONT         SN         Control     Run      Config   Match    Protect\n"
            . "          ID                     flag        state    state    state    side\n"
            . $sep
            . $rows
            . $sep
            . "  F/S/P   ONT-ID   Description\n"
            . $sep
            . $descs
            . $sep
            . "  In port 0/ 3/2 , the total of ONTs are: {$count}, online: 1\n";
    }

    // ── Empty / no ONTs ───────────────────────────────────────────────────────

    public function test_empty_port_returns_empty_array(): void
    {
        $output = <<<'EOT'
  Command:
            display ont info 2 all
    -----------------------------------------------------------------------------
    F/S/P   ONT         SN         Control     Run      Config   Match    Protect
            ID                     flag        state    state    state    side
    -----------------------------------------------------------------------------
    -----------------------------------------------------------------------------
    F/S/P   ONT-ID   Description
    -----------------------------------------------------------------------------
    -----------------------------------------------------------------------------
    In port 0/ 3/2 , the total of ONTs are: 0, online: 0
  EOT;

        $this->assertSame([], OntListParser::parse($output));
    }

    public function test_failure_message_returns_empty_array(): void
    {
        $output = "Failure: No related information to be displayed";
        $this->assertSame([], OntListParser::parse($output));
    }

    public function test_do_not_exist_message_returns_empty_array(): void
    {
        $output = "  The port do not exist.\n";
        $this->assertSame([], OntListParser::parse($output));
    }

    // ── Single ONT (synthetic) ────────────────────────────────────────────────

    public function test_parses_single_row_correctly(): void
    {
        $output = <<<'EOT'
  Command:
            display ont info 2 all
    -----------------------------------------------------------------------------
    F/S/P   ONT         SN         Control     Run      Config   Match    Protect
            ID                     flag        state    state    state    side
    -----------------------------------------------------------------------------
    0/ 3/2    0  48575443FEFCC9A2  active      online   normal   mismatch no
    -----------------------------------------------------------------------------
    F/S/P   ONT-ID   Description
    -----------------------------------------------------------------------------
    0/ 3/2       0   FIBER_CLIENT_01
    -----------------------------------------------------------------------------
    In port 0/ 3/2 , the total of ONTs are: 1, online: 1
  EOT;

        $result = OntListParser::parse($output);

        $this->assertCount(1, $result);
        $ont = $result[0];
        $this->assertSame(0,            $ont['ont_id']);
        $this->assertSame('48575443FEFCC9A2', $ont['sn']);
        $this->assertSame('active',     $ont['control_flag']);
        $this->assertSame('online',     $ont['run_state']);
        $this->assertSame('normal',     $ont['config_state']);
        $this->assertSame('mismatch',   $ont['match_state']);
        $this->assertSame('FIBER_CLIENT_01', $ont['description']);
    }

    public function test_offline_state_parsed(): void
    {
        $output = <<<'EOT'
    -----------------------------------------------------------------------------
    F/S/P   ONT         SN         Control     Run      Config   Match    Protect
            ID                     flag        state    state    state    side
    -----------------------------------------------------------------------------
    0/ 3/2    5  48575443AABBCCDD  active      offline  match    match    no
    -----------------------------------------------------------------------------
    F/S/P   ONT-ID   Description
    -----------------------------------------------------------------------------
    0/ 3/2       5
    -----------------------------------------------------------------------------
    In port 0/ 3/2 , the total of ONTs are: 1, online: 0
  EOT;

        $result = OntListParser::parse($output);
        $this->assertCount(1, $result);
        $this->assertSame('offline', $result[0]['run_state']);
        $this->assertSame(5,         $result[0]['ont_id']);
    }

    public function test_multiple_onts_parsed(): void
    {
        $result = OntListParser::parse(self::syntheticOutput(3));

        $this->assertCount(3, $result);
        $this->assertSame(0, $result[0]['ont_id']);
        $this->assertSame(1, $result[1]['ont_id']);
        $this->assertSame(2, $result[2]['ont_id']);
    }

    public function test_sn_uppercased(): void
    {
        $output = <<<'EOT'
    -----------------------------------------------------------------------------
    F/S/P   ONT         SN         Control     Run      Config   Match    Protect
            ID                     flag        state    state    state    side
    -----------------------------------------------------------------------------
    0/ 3/2    0  48575443fefcc9a2  active      online   normal   match    no
    -----------------------------------------------------------------------------
    F/S/P   ONT-ID   Description
    -----------------------------------------------------------------------------
    0/ 3/2       0
    -----------------------------------------------------------------------------
    In port 0/ 3/2 , the total of ONTs are: 1, online: 1
  EOT;

        $result = OntListParser::parse($output);
        $this->assertSame('48575443FEFCC9A2', $result[0]['sn']);
    }

    // ── Real fixture ──────────────────────────────────────────────────────────

    public function test_real_fixture_parses_one_ont(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $this->assertCount(1, $result, 'Expected exactly 1 ONT from the real fixture');
    }

    public function test_real_fixture_ont_id_is_zero(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $this->assertSame(0, $result[0]['ont_id']);
    }

    public function test_real_fixture_sn_matches_authorized_onu(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        // 48575443 = "HWTC" in ASCII → vendor prefix
        // FEFCC9A2 → suffix matching HWTCFEFCC9A2
        $this->assertStringContainsString('FEFCC9A2', $result[0]['sn']);
    }

    public function test_real_fixture_run_state_online(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $this->assertSame('online', $result[0]['run_state']);
    }

    public function test_real_fixture_control_flag_active(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $this->assertSame('active', $result[0]['control_flag']);
    }

    public function test_real_fixture_description_merged(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $this->assertNotEmpty($result[0]['description'], 'Description should be merged from section 2');
        $this->assertStringContainsString('PRUEBA', $result[0]['description']);
    }

    public function test_result_has_expected_keys(): void
    {
        $result = OntListParser::parse(self::fixture('display_ont_info_port_real.txt'));

        $keys = ['ont_id', 'sn', 'control_flag', 'run_state', 'config_state', 'match_state', 'description'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $result[0], "Missing key: {$key}");
        }
    }
}
