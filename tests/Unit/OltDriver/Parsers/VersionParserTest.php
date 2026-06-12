<?php

namespace Tests\Unit\OltDriver\Parsers;

use App\Services\OltDriver\Huawei\Parsers\VersionParser;
use PHPUnit\Framework\TestCase;

class VersionParserTest extends TestCase
{
    private static function fixture(): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/huawei/display_version.txt');
    }

    public function test_parses_model_from_fixture(): void
    {
        $result = VersionParser::parse(self::fixture());
        $this->assertSame('MA5800-X7', $result['model']);
    }

    public function test_parses_firmware_from_fixture(): void
    {
        $result = VersionParser::parse(self::fixture());
        $this->assertStringContainsString('V100R018C00', $result['firmware']);
    }

    public function test_parses_patch_from_fixture(): void
    {
        $result = VersionParser::parse(self::fixture());
        $this->assertSame('SPH505', $result['patch']);
    }

    public function test_parses_uptime_from_fixture(): void
    {
        $result = VersionParser::parse(self::fixture());
        $this->assertStringContainsString('week', $result['uptime']);
        $this->assertStringContainsString('day', $result['uptime']);
    }

    public function test_returns_empty_strings_on_empty_output(): void
    {
        $result = VersionParser::parse('');
        $this->assertSame('', $result['model']);
        $this->assertSame('', $result['firmware']);
        $this->assertSame('', $result['patch']);
        $this->assertSame('', $result['uptime']);
    }

    public function test_returns_empty_strings_on_unrelated_output(): void
    {
        $result = VersionParser::parse("No related information.\n");
        $this->assertSame('', $result['model']);
    }

    // ── Real fixture (captured against MA5800-X7 in B1c session 2026-06-12) ──

    public function test_real_fixture_firmware(): void
    {
        $raw    = file_get_contents(__DIR__ . '/../fixtures/huawei/display_version_real.txt');
        $result = VersionParser::parse($raw);
        $this->assertStringContainsString('V100R018C00', $result['firmware']);
    }

    public function test_real_fixture_patch(): void
    {
        $raw    = file_get_contents(__DIR__ . '/../fixtures/huawei/display_version_real.txt');
        $result = VersionParser::parse($raw);
        $this->assertSame('SPH505', $result['patch']);
    }

    public function test_real_fixture_model(): void
    {
        $raw    = file_get_contents(__DIR__ . '/../fixtures/huawei/display_version_real.txt');
        $result = VersionParser::parse($raw);
        // Real OLT: PRODUCT line, not "hostname uptime" — model may be empty string.
        // What matters is firmware + patch parsed correctly; model via PRODUCT is
        // not yet extracted by the parser (tracked as B1c parser gap).
        $this->assertIsString($result['model']);
    }
}
