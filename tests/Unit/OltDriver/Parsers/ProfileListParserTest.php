<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\ProfileListParser;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test — no DB, no transport, no network.
 * Uses W0 fixtures captured from the real MA5800-X7.
 */
class ProfileListParserTest extends TestCase
{
    // ── Line-profile fixture ─────────────────────────────────────────────────

    private static function lineFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_lineprofile_gpon_all.txt');
    }

    private static function srvFixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_srvprofile_gpon_all.txt');
    }

    // ── Line-profile parsing ─────────────────────────────────────────────────

    public function test_line_profile_returns_correct_count(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        $this->assertCount(16, $profiles);
    }

    public function test_line_profile_id_0_is_parsed(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        $this->assertSame(['id' => 0, 'name' => 'line-profile_default_0', 'bindings' => 0], $profiles[0]);
    }

    public function test_line_profile_1_smartolt_flexible(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        $p = ProfileListParser::findById($profiles, 1);
        $this->assertNotNull($p);
        $this->assertSame('SMARTOLT_FLEXIBLE_GPON', $p['name']);
        $this->assertSame(2206, $p['bindings']);
    }

    public function test_line_profile_high_id_8193(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        $p = ProfileListParser::findById($profiles, 8193);
        $this->assertNotNull($p);
        $this->assertSame('line-profile-extend-frame', $p['name']);
    }

    public function test_line_profile_find_by_id_returns_null_for_missing(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        $this->assertNull(ProfileListParser::findById($profiles, 9999));
    }

    public function test_line_profile_separator_lines_excluded(): void
    {
        $profiles = ProfileListParser::parse(self::lineFixture());
        foreach ($profiles as $p) {
            $this->assertIsInt($p['id']);
            $this->assertIsString($p['name']);
            $this->assertNotEmpty($p['name']);
        }
    }

    // ── Srv-profile parsing ──────────────────────────────────────────────────

    public function test_srv_profile_returns_correct_count(): void
    {
        $profiles = ProfileListParser::parse(self::srvFixture());
        $this->assertCount(40, $profiles);
    }

    public function test_srv_profile_2_hg8145v5(): void
    {
        $profiles = ProfileListParser::parse(self::srvFixture());
        $p = ProfileListParser::findById($profiles, 2);
        $this->assertNotNull($p);
        $this->assertSame('HG8145V5', $p['name']);
        $this->assertSame(711, $p['bindings']);
    }

    public function test_srv_profile_handles_heavy_indent_line_20(): void
    {
        // Profile 20 in the W0 fixture has excessive leading whitespace
        $profiles = ProfileListParser::parse(self::srvFixture());
        $p = ProfileListParser::findById($profiles, 20);
        $this->assertNotNull($p);
        $this->assertSame('Generic_2_V101', $p['name']);
    }

    public function test_srv_profile_all_entries_have_required_keys(): void
    {
        $profiles = ProfileListParser::parse(self::srvFixture());
        foreach ($profiles as $p) {
            $this->assertArrayHasKey('id', $p);
            $this->assertArrayHasKey('name', $p);
            $this->assertArrayHasKey('bindings', $p);
            $this->assertIsInt($p['id']);
            $this->assertIsInt($p['bindings']);
            $this->assertIsString($p['name']);
        }
    }

    // ── Edge cases ───────────────────────────────────────────────────────────

    public function test_empty_output_returns_empty_array(): void
    {
        $this->assertSame([], ProfileListParser::parse(''));
    }

    public function test_find_by_id_on_empty_array_returns_null(): void
    {
        $this->assertNull(ProfileListParser::findById([], 1));
    }
}
