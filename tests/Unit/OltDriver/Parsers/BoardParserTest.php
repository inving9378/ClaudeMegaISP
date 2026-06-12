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

    public function test_h902gphf_has_16_ports(): void
    {
        // H902GPHF is a 16-port GPON board (same port count as H901GPHF; differs only
        // in transceiver class — C+++ vs C++). Confirmed by port 0/2/8 holding 112 ONTs.
        $boards = array_values(array_filter(self::parsed(), fn($b) => $b['board'] === 'H902GPHF'));
        $this->assertNotEmpty($boards);
        foreach ($boards as $b) {
            $this->assertSame(16, $b['port_count']);
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

    // ── Real fixture (MA5800-X7, B2a 2026-06-12) ─────────────────────────────

    private static function realFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_board_real.txt');
    }

    private static function realParsed(): array
    {
        return BoardParser::parse(self::realFixture());
    }

    public function test_real_fixture_parses_nine_boards(): void
    {
        // Slots 0, 6, 7 are empty (no board installed) and are correctly skipped.
        $this->assertCount(9, self::realParsed());
    }

    public function test_real_fixture_identifies_five_gpon_boards(): void
    {
        $gpon = array_filter(self::realParsed(), fn($b) => $b['is_gpon']);
        $this->assertCount(5, $gpon);
    }

    public function test_real_fixture_gpon_slots_are_1_through_5(): void
    {
        $gponSlots = array_column(
            array_values(array_filter(self::realParsed(), fn($b) => $b['is_gpon'])),
            'slot'
        );
        sort($gponSlots);
        $this->assertSame([1, 2, 3, 4, 5], $gponSlots);
    }

    public function test_real_fixture_h902gphf_slots_have_16_ports(): void
    {
        // Slots 1 and 2 are H902GPHF — 16 PON ports each.
        // Bug-fix: was incorrectly set to 8. Empirical proof: port 0/2/8 (slot 2)
        // holds 112 ONTs (port 8 cannot exist on an 8-port card); SmartOLT confirms
        // ports 0-15 on those slots.
        $boards = array_values(array_filter(self::realParsed(), fn($b) => $b['board'] === 'H902GPHF'));
        $this->assertCount(2, $boards);
        foreach ($boards as $b) {
            $this->assertSame(16, $b['port_count']);
        }
    }

    public function test_real_fixture_h901gphf_slots_have_16_ports(): void
    {
        // Slots 3, 4, 5 are H901GPHF (16 PON ports each)
        $boards = array_values(array_filter(self::realParsed(), fn($b) => $b['board'] === 'H901GPHF'));
        $this->assertCount(3, $boards);
        foreach ($boards as $b) {
            $this->assertSame(16, $b['port_count']);
        }
    }

    public function test_real_fixture_active_master_is_slot9(): void
    {
        // VRP V100R018C00 SPH505 reports "Active_normal" (not "Active_Master") for H902MPLA.
        // Both forms have been seen in the wild — depends on firmware patch level.
        $slot9 = array_values(array_filter(self::realParsed(), fn($b) => $b['slot'] === 9));
        $this->assertCount(1, $slot9);
        $this->assertStringContainsStringIgnoringCase('active', $slot9[0]['status']);
        $this->assertFalse($slot9[0]['is_gpon']);
    }
}
