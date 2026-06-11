<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\AutofindParser;
use PHPUnit\Framework\TestCase;

class AutofindParserTest extends TestCase
{
    private static function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_autofind.txt');
    }

    private static function emptyFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_ont_autofind_empty.txt');
    }

    public function test_parses_one_entry_from_fixture(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertCount(1, $entries);
    }

    public function test_sn_parsed_correctly(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertSame('HWTC12345678', $entries[0]['sn']);
    }

    public function test_port_parsed_correctly(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertSame('0/1/0', $entries[0]['port']);
    }

    public function test_vender_id_parsed(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertSame('HWTC', $entries[0]['vender_id']);
    }

    public function test_equipment_id_parsed(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertSame('EG8145V5', $entries[0]['equipment_id']);
    }

    public function test_autofind_time_parsed(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $this->assertStringContainsString('2026-06-10', $entries[0]['autofind_time']);
    }

    public function test_empty_returns_empty_array(): void
    {
        $this->assertSame([], AutofindParser::parse(self::emptyFixture()));
    }

    public function test_blank_string_returns_empty_array(): void
    {
        $this->assertSame([], AutofindParser::parse(''));
    }

    public function test_all_entries_have_required_keys(): void
    {
        $entries = AutofindParser::parse(self::fixture());
        $required = ['sn', 'port', 'vender_id', 'equipment_id', 'autofind_time'];
        foreach ($entries as $entry) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $entry, "Missing key: {$key}");
            }
        }
    }
}
