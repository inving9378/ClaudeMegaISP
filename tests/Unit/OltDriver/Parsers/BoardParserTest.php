<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\BoardParser;
use PHPUnit\Framework\TestCase;

class BoardParserTest extends TestCase
{
    private static function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_board_0.txt');
    }

    private static function parsed(): array
    {
        return BoardParser::parse(self::fixture());
    }

    public function test_parses_nine_board_entries(): void
    {
        $this->assertCount(9, self::parsed());
    }

    public function test_identifies_five_gpon_boards(): void
    {
        $gpon = array_filter(self::parsed(), fn($b) => $b['is_gpon']);
        $this->assertCount(5, $gpon);
    }

    public function test_h902gphf_has_8_ports(): void
    {
        $boards = array_values(array_filter(self::parsed(), fn($b) => $b['board'] === 'H902GPHF'));
        $this->assertNotEmpty($boards);
        foreach ($boards as $b) {
            $this->assertSame(8, $b['port_count']);
        }
    }

    public function test_h901gphf_has_16_ports(): void
    {
        $boards = array_values(array_filter(self::parsed(), fn($b) => $b['board'] === 'H901GPHF'));
        $this->assertNotEmpty($boards);
        foreach ($boards as $b) {
            $this->assertSame(16, $b['port_count']);
        }
    }

    public function test_slot_numbers_are_correct(): void
    {
        $slots = array_column(self::parsed(), 'slot');
        $this->assertContains(1, $slots);
        $this->assertContains(5, $slots);
        $this->assertContains(8, $slots);
        $this->assertContains(11, $slots);
    }

    public function test_control_boards_are_not_gpon(): void
    {
        $mpla = array_values(array_filter(self::parsed(), fn($b) => $b['board'] === 'H902MPLA'));
        $this->assertNotEmpty($mpla);
        foreach ($mpla as $b) {
            $this->assertFalse($b['is_gpon']);
            $this->assertSame(0, $b['port_count']);
        }
    }

    public function test_active_master_status_parsed(): void
    {
        $mpla = array_values(array_filter(self::parsed(), fn($b) => $b['slot'] === 8));
        $this->assertNotEmpty($mpla);
        $this->assertSame('Active_Master', $mpla[0]['status']);
    }

    public function test_empty_output_returns_empty_array(): void
    {
        $this->assertSame([], BoardParser::parse(''));
    }

    public function test_is_gpon_board_helper(): void
    {
        $this->assertTrue(BoardParser::isGponBoard('H902GPHF'));
        $this->assertTrue(BoardParser::isGponBoard('H901GPHF'));
        $this->assertFalse(BoardParser::isGponBoard('H902MPLA'));
        $this->assertFalse(BoardParser::isGponBoard('H901PILA'));
    }
}
