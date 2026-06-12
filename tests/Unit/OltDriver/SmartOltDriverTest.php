<?php

namespace Tests\Unit\OltDriver;

use App\Services\OLTsService;
use App\Services\OltDriver\OltDriverInterface;
use App\Services\OltDriver\SmartOltDriver;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SmartOltDriver — verifies that it implements OltDriverInterface
 * and delegates correctly to OLTsService.
 *
 * Only the methods that have non-trivial adapter logic are tested here.
 * Pass-through delegations (getName, listOlts, etc.) are covered by integration tests.
 */
class SmartOltDriverTest extends TestCase
{
    private OLTsService    $service;
    private SmartOltDriver $driver;

    protected function setUp(): void
    {
        $this->service = $this->createMock(OLTsService::class);
        $this->driver  = new SmartOltDriver($this->service);
    }

    // ── Contract ──────────────────────────────────────────────────────────────

    public function test_implements_olt_driver_interface(): void
    {
        $this->assertInstanceOf(OltDriverInterface::class, $this->driver);
    }

    public function test_get_name_returns_smartolt(): void
    {
        $this->assertSame('SmartOLT', $this->driver->getName());
    }

    // ── findOnuBySn ───────────────────────────────────────────────────────────

    public function test_find_onu_by_sn_success_with_onu_details_key(): void
    {
        $onuDetails = [
            'sn'                 => 'HWTCFEFCC9A2',
            'unique_external_id' => 'onu-ext-123',
            'olt_id'             => '1',
            'board'              => '3',
            'port'               => '2',
            'onu'                => 0,
            'status'             => 'Online',
            'signal'             => 'Very good',
            'signal_1310'        => '-13.04',
            'signal_1490'        => '-8.54',
        ];

        $this->service->expects($this->once())
            ->method('getOnuDetailsBySN')
            ->with('HWTCFEFCC9A2')
            ->willReturn(['success' => true, 'onu_details' => $onuDetails]);

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertTrue($result['success']);
        $this->assertSame($onuDetails, $result['onu']);
    }

    public function test_find_onu_by_sn_success_with_onu_key(): void
    {
        // Some SmartOLT API versions may return 'onu' instead of 'onu_details'
        $onuDetails = ['sn' => 'HWTCFEFCC9A2', 'status' => 'Online'];

        $this->service->method('getOnuDetailsBySN')
            ->willReturn(['success' => true, 'onu' => $onuDetails]);

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertTrue($result['success']);
        $this->assertSame($onuDetails, $result['onu']);
    }

    public function test_find_onu_by_sn_not_found_returns_failure(): void
    {
        $this->service->method('getOnuDetailsBySN')
            ->willReturn(['success' => false, 'message' => 'ONU not found']);

        $result = $this->driver->findOnuBySn('UNKNOWNSN123');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('not found', strtolower($result['message']));
    }

    public function test_find_onu_by_sn_failure_without_message_uses_default(): void
    {
        $this->service->method('getOnuDetailsBySN')
            ->willReturn(['success' => false]);

        $result = $this->driver->findOnuBySn('UNKNOWNSN999');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('UNKNOWNSN999', $result['message']);
    }

    public function test_find_onu_by_sn_success_but_no_onu_key_returns_failure(): void
    {
        // Guard against malformed API response that claims success but has no ONU data
        $this->service->method('getOnuDetailsBySN')
            ->willReturn(['success' => true]);

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HWTCFEFCC9A2', $result['message']);
    }

    public function test_find_onu_by_sn_delegates_to_get_onu_details_by_sn(): void
    {
        $this->service->expects($this->once())
            ->method('getOnuDetailsBySN')
            ->with('ECOMDEADBEEF')
            ->willReturn(['success' => false]);

        $this->driver->findOnuBySn('ECOMDEADBEEF');
    }
}
