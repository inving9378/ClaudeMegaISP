<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\WriteNotEnabledException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Pure unit test — PHPUnit\Framework\TestCase, no DB, no network.
 * HuaweiTransport is mocked (createMock bypasses the real constructor).
 */
class HuaweiDriverTest extends TestCase
{
    private HuaweiTransport $transport;
    private HuaweiDriver    $driver;

    private static string $boardFixture;
    private static string $ontPortFixture;        // detailed per-ONT (OntInfoParser) — for getOnuStatus/getOnuDetails
    private static string $ontTabularFixture;     // tabular batch (OntListParser) — for getOnusByOlt/getOnusStatus
    private static string $ontEmptyFixture;
    private static string $versionFixture;
    private static string $autofindFixture;
    private static string $autofindEmptyFixture;
    private static string $opticalFixture;
    private static string $dbaFixture;
    private static string $bySNFixture;           // real MA5800-X7 by-sn output — for findOnuBySn

    public static function setUpBeforeClass(): void
    {
        $base = __DIR__ . '/fixtures/huawei/';
        self::$boardFixture         = file_get_contents($base . 'display_board_0.txt');
        self::$ontPortFixture       = file_get_contents($base . 'display_ont_info_port.txt');
        self::$ontTabularFixture    = file_get_contents($base . 'display_ont_info_tabular.txt');
        self::$ontEmptyFixture      = file_get_contents($base . 'display_ont_info_empty_port.txt');
        self::$versionFixture       = file_get_contents($base . 'display_version.txt');
        self::$autofindFixture      = file_get_contents($base . 'display_ont_autofind.txt');
        self::$autofindEmptyFixture = file_get_contents($base . 'display_ont_autofind_empty.txt');
        self::$opticalFixture       = file_get_contents($base . 'display_ont_optical_info.txt');
        self::$dbaFixture           = file_get_contents($base . 'display_dba_profile_all.txt');
        self::$bySNFixture          = file_get_contents($base . 'display_ont_info_by_sn_real.txt');
    }

    protected function setUp(): void
    {
        $this->transport = $this->createMock(HuaweiTransport::class);
        // Unit tests assume the transport is already open (pre-condition).
        // withSession() checks isOpen() to decide whether to bypass Cache::lock().
        $this->transport->method('isOpen')->willReturn(true);
        $this->driver    = new HuaweiDriver(
            transport: $this->transport,
            config:    ['olt_id' => '1', 'name' => 'MA5800-TEST', 'frame' => 0],
            logger:    new NullLogger(),
        );
    }

    // ── getName() ─────────────────────────────────────────────────────────────

    public function test_get_name_returns_huawei(): void
    {
        $this->assertSame('Huawei', $this->driver->getName());
    }

    // ── listOlts() ────────────────────────────────────────────────────────────

    public function test_list_olts_returns_one_olt_with_correct_id(): void
    {
        $this->transport->method('exec')->willReturn(self::$versionFixture);

        $result = $this->driver->listOlts();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('1', $result['data'][0]['id']);
        $this->assertSame('MA5800-TEST', $result['data'][0]['name']);
    }

    public function test_list_olts_includes_firmware_and_patch(): void
    {
        $this->transport->method('exec')->willReturn(self::$versionFixture);

        $result = $this->driver->listOlts();

        $olt = $result['data'][0];
        $this->assertStringContainsString('V100R018C00', $olt['firmware']);
        $this->assertSame('SPH505', $olt['patch']);
    }

    // ── listSpeedProfiles() ───────────────────────────────────────────────────

    public function test_list_speed_profiles_returns_profiles(): void
    {
        $this->transport->method('exec')->willReturn(self::$dbaFixture);

        $result = $this->driver->listSpeedProfiles();

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['response']);
        // First profile
        $p = $result['response'][0];
        $this->assertArrayHasKey('id', $p);
        $this->assertArrayHasKey('name', $p);
        $this->assertArrayHasKey('speed', $p);
        $this->assertArrayHasKey('direction', $p);
        $this->assertArrayHasKey('type', $p);
    }

    public function test_list_speed_profiles_count(): void
    {
        $this->transport->method('exec')->willReturn(self::$dbaFixture);

        $result = $this->driver->listSpeedProfiles();

        $this->assertCount(10, $result['response']);
    }

    // ── getUnconfiguredOnus() ─────────────────────────────────────────────────

    public function test_get_unconfigured_onus_returns_one_entry(): void
    {
        $this->transport->method('exec')->willReturn(self::$autofindFixture);

        $result = $this->driver->getUnconfiguredOnus();

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['response']);
        $this->assertSame('HWTC12345678', $result['response'][0]['sn']);
    }

    public function test_get_unconfigured_onus_empty_when_none(): void
    {
        $this->transport->method('exec')->willReturn(self::$autofindEmptyFixture);

        $result = $this->driver->getUnconfiguredOnus();

        $this->assertTrue($result['success']);
        $this->assertSame([], $result['response']);
    }

    public function test_get_unconfigured_onus_rejects_foreign_olt(): void
    {
        $result = $this->driver->getUnconfiguredOnus('other-olt');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('other-olt', $result['message']);
    }

    // ── getOnusByOlt() ────────────────────────────────────────────────────────

    public function test_get_onus_by_olt_returns_success(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('onus', $result);
    }

    public function test_get_onus_by_olt_finds_reference_onu(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCFEFCC9A2');

        $this->assertNotNull($ref, 'Reference ONT HWTCFEFCC9A2 not found');
    }

    public function test_get_onus_by_olt_normalizes_unique_external_id(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCFEFCC9A2');

        $this->assertSame('1:0/3/2:0', $ref['unique_external_id']);
    }

    public function test_get_onus_by_olt_shape_is_complete(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCFEFCC9A2');

        $required = ['sn', 'unique_external_id', 'olt_id', 'board', 'port', 'onu', 'status',
                     'signal', 'signal_1310', 'signal_1490'];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $ref, "Missing shape key: {$key}");
        }
    }

    public function test_get_onus_by_olt_status_online_for_reference_onu(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCFEFCC9A2');

        $this->assertSame('Online', $ref['status']);
    }

    public function test_get_onus_by_olt_second_onu_is_offline(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCAABBCC11');

        $this->assertNotNull($ref);
        $this->assertSame('Offline', $ref['status']);
    }

    public function test_get_onus_by_olt_board_and_port_values(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusByOlt('1');
        $ref    = $this->findBySn($result['onus'], 'HWTCFEFCC9A2');

        $this->assertSame('3', $ref['board']);
        $this->assertSame('2', $ref['port']);
        $this->assertSame(0, $ref['onu']);
    }

    public function test_get_onus_by_olt_rejects_foreign_olt(): void
    {
        $result = $this->driver->getOnusByOlt('other-olt');

        $this->assertFalse($result['success']);
    }

    // ── getOnusStatus() ───────────────────────────────────────────────────────

    public function test_get_onus_status_shape(): void
    {
        $this->setupBoardAndOntMock();

        $result = $this->driver->getOnusStatus('1');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response', $result);

        $required = ['sn', 'olt_id', 'unique_external_id', 'board', 'port', 'onu', 'zone_id', 'status'];
        foreach ($result['response'] as $item) {
            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $item, "Missing status key: {$key}");
            }
        }
    }

    // ── getOnuStatus() ────────────────────────────────────────────────────────

    public function test_get_onu_status_online(): void
    {
        $this->transport->method('exec')->willReturn(self::$ontPortFixture);

        $result = $this->driver->getOnuStatus('1:0/3/2:0');

        $this->assertTrue($result['success']);
        $this->assertSame('Online', $result['onu_status']);
        $this->assertArrayHasKey('last_status_change', $result);
    }

    public function test_get_onu_status_not_found_returns_failure(): void
    {
        $this->transport->method('exec')->willReturn('  No related information.');

        $result = $this->driver->getOnuStatus('1:0/3/2:99');

        $this->assertFalse($result['success']);
    }

    // ── getOnuSignal() @pending-validation ────────────────────────────────────

    public function test_get_onu_signal_shape(): void
    {
        $this->transport->expects($this->once())->method('enterGponInterface');
        $this->transport->method('exec')->willReturn(self::$opticalFixture);
        $this->transport->expects($this->once())->method('leaveToUserView');

        $result = $this->driver->getOnuSignal('1:0/3/2:0');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('onu_signal', $result);
        $this->assertArrayHasKey('onu_signal_1310', $result);
        $this->assertArrayHasKey('onu_signal_1490', $result);
    }

    public function test_get_onu_signal_values_from_ground_truth_fixture(): void
    {
        $this->transport->method('enterGponInterface');
        $this->transport->method('exec')->willReturn(self::$opticalFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->getOnuSignal('1:0/3/2:0');

        // Ground truth: Rx ONU -8.54 → signal_1490; Rx OLT -13.04 → signal_1310
        $this->assertSame('-8.54', $result['onu_signal_1490']);
        $this->assertSame('-13.04', $result['onu_signal_1310']);
        $this->assertSame('Warning', $result['onu_signal']); // -8.54 dBm → Warning range
    }

    // ── getOnuDetails() ───────────────────────────────────────────────────────

    public function test_get_onu_details_shape(): void
    {
        $this->transport->method('enterGponInterface');
        $this->transport->method('leaveToUserView');
        $this->transport->method('exec')
            ->willReturnCallback(fn($cmd) => str_contains($cmd, 'optical') ? self::$opticalFixture : self::$ontPortFixture);

        $result = $this->driver->getOnuDetails('1:0/3/2:0');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('onu_details', $result);

        $required = ['sn', 'unique_external_id', 'olt_id', 'olt_name', 'board', 'port', 'onu',
                     'status', 'signal', 'signal_1310', 'signal_1490', 'mode', 'wan_mode'];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $result['onu_details'], "Missing detail key: {$key}");
        }
    }

    // ── Write methods must throw WriteNotEnabledException ────────────────────

    /** @dataProvider writeMethods */
    public function test_write_methods_throw_write_not_enabled(string $method, array $args): void
    {
        $this->expectException(WriteNotEnabledException::class);
        $this->driver->{$method}(...$args);
    }

    public static function writeMethods(): array
    {
        return [
            'authorizeOnu'        => ['authorizeOnu',        [[]]],
            'deauthorizeOnu'      => ['deauthorizeOnu',      ['1:0/3/2:0']],
            'setOnuEnabled'       => ['setOnuEnabled',       ['1:0/3/2:0', true]],
            'rebootOnu'           => ['rebootOnu',           ['1:0/3/2:0']],
            'setOnuSpeedProfile'  => ['setOnuSpeedProfile',  ['1:0/3/2:0', []]],
        ];
    }

    public function test_write_not_enabled_exception_message_contains_operation(): void
    {
        try {
            $this->driver->rebootOnu('1:0/3/2:0');
            $this->fail('Expected WriteNotEnabledException');
        } catch (WriteNotEnabledException $e) {
            $this->assertStringContainsString('rebootOnu', $e->getMessage());
            $this->assertStringContainsString('B4', $e->getMessage());
        }
    }

    // ── findOnuBySn() ─────────────────────────────────────────────────────────

    public function test_find_onu_by_sn_returns_success(): void
    {
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('onu', $result);
    }

    public function test_find_onu_by_sn_sn_normalized_to_decoded_form(): void
    {
        // Fixture SN line: "48575443FEFCC9A2 (HWTC-FEFCC9A2)"
        // After normalizeSn(): strip parenthetical → hex → decode → "HWTCFEFCC9A2"
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertSame('HWTCFEFCC9A2', $result['onu']['sn']);
    }

    public function test_find_onu_by_sn_fsp_correctly_extracted(): void
    {
        // Fixture F/S/P line: "F/S/P : 0/3/2"
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertSame('3', $result['onu']['board']);
        $this->assertSame('2', $result['onu']['port']);
        $this->assertSame(0, $result['onu']['onu']);
    }

    public function test_find_onu_by_sn_status_online(): void
    {
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertSame('Online', $result['onu']['status']);
    }

    public function test_find_onu_by_sn_unique_external_id_format(): void
    {
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('HWTCFEFCC9A2');

        $this->assertSame('1:0/3/2:0', $result['onu']['unique_external_id']);
    }

    public function test_find_onu_by_sn_not_found_returns_failure(): void
    {
        $this->transport->method('enterConfigView');
        $this->transport->method('exec')->willReturn('  Failure: The ONT does not exist.');
        $this->transport->method('leaveToUserView');

        $result = $this->driver->findOnuBySn('DEADBEEF0000');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_find_onu_by_sn_calls_enter_and_leave_config_view(): void
    {
        $this->transport->expects($this->once())->method('enterConfigView');
        $this->transport->method('exec')->willReturn(self::$bySNFixture);
        $this->transport->expects($this->once())->method('leaveToUserView');

        $this->driver->findOnuBySn('HWTCFEFCC9A2');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Sets up the transport mock for bulk-ONT methods (getOnusByOlt, getOnusStatus).
     *
     * collectAllOnts() now uses:
     *   enterGponInterface(frame, slot)  — sets the interface context
     *   exec("display ont info {port} all")  — interface-context syntax
     *
     * We track the current slot through a captured reference so the exec callback
     * can return the tabular fixture only for the known port (slot=3, port=2).
     */
    private function setupBoardAndOntMock(): void
    {
        $currentSlot = 0;

        $this->transport->method('enterGponInterface')
            ->willReturnCallback(function (int $frame, int $slot) use (&$currentSlot): void {
                $currentSlot = $slot;
            });

        $this->transport->method('exec')
            ->willReturnCallback(function (string $cmd) use (&$currentSlot): string {
                if (str_contains($cmd, 'display board')) {
                    return self::$boardFixture;
                }
                // Interface-context: "display ont info {port} all"
                if (preg_match('/^display ont info (\d+) all$/', trim($cmd), $m)) {
                    return ($currentSlot === 3 && (int) $m[1] === 2)
                        ? self::$ontTabularFixture
                        : self::$ontEmptyFixture;
                }
                return '';
            });
    }

    private function findBySn(array $onus, string $sn): ?array
    {
        foreach ($onus as $onu) {
            if ($onu['sn'] === $sn) {
                return $onu;
            }
        }
        return null;
    }
}
