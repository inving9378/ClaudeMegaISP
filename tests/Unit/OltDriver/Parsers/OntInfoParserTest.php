<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\OntInfoParser;
use PHPUnit\Framework\TestCase;

class OntInfoParserTest extends TestCase
{
    private static function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_info_port.txt');
    }

    private static function emptyFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_info_empty_port.txt');
    }

    public function test_parses_two_onts_from_fixture(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertCount(2, $onts);
    }

    public function test_first_ont_is_reference_onu(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('HWTCFEFCC9A2', $onts[0]['sn']);
        $this->assertSame(0, $onts[0]['ont_id']);
    }

    public function test_first_ont_is_online(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('online', $onts[0]['run_state']);
    }

    public function test_first_ont_control_flag_is_active(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('active', $onts[0]['control_flag']);
    }

    public function test_first_ont_config_state_is_normal(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('normal', $onts[0]['config_state']);
    }

    public function test_first_ont_description_anonymized(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('CLIENTE_1', $onts[0]['description']);
    }

    public function test_first_ont_distance_parsed(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame(1143, $onts[0]['distance_m']);
    }

    public function test_first_ont_last_up_time_parsed(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertStringContainsString('2026-06-10', $onts[0]['last_up_time']);
    }

    public function test_second_ont_is_offline(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $this->assertSame('HWTCAABBCC11', $onts[1]['sn']);
        $this->assertSame(1, $onts[1]['ont_id']);
        $this->assertSame('offline', $onts[1]['run_state']);
    }

    public function test_empty_port_returns_empty_array(): void
    {
        $onts = OntInfoParser::parse(self::emptyFixture());
        $this->assertSame([], $onts);
    }

    public function test_empty_string_returns_empty_array(): void
    {
        $this->assertSame([], OntInfoParser::parse(''));
    }

    public function test_all_entries_have_required_keys(): void
    {
        $onts = OntInfoParser::parse(self::fixture());
        $required = ['ont_id', 'sn', 'run_state', 'control_flag', 'config_state', 'description',
                     'last_up_time', 'last_down_time', 'last_down_cause', 'distance_m'];
        foreach ($onts as $ont) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $ont, "Missing key: {$key}");
            }
        }
    }
}
