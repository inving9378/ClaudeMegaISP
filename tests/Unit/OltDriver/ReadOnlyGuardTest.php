<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\ReadOnlyViolationException;
use App\Services\OltDriver\Huawei\WriteTargetDeniedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * Pure unit test — no Laravel bootstrap, no DB, no migrations.
 * Uses PHPUnit\Framework\TestCase directly (not Tests\TestCase)
 * to avoid the migrate:fresh --seed that would wipe the production DB.
 */
class ReadOnlyGuardTest extends TestCase
{
    private ReadOnlyGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new ReadOnlyGuard(new NullLogger());
    }

    // ── ALLOW battery ────────────────────────────────────────────────────────

    /** @dataProvider allowedCommands */
    public function test_allows_whitelisted_commands(string $cmd): void
    {
        $this->guard->assertAllowed($cmd);
        $this->assertTrue(true); // reaching here means no exception was thrown
    }

    public static function allowedCommands(): array
    {
        return [
            // ---- display commands ----
            'display version'                        => ['display version'],
            'display board 0'                        => ['display board 0'],
            'display ont info 0 1 0 all'             => ['display ont info 0 1 0 all'],
            'display current-configuration'          => ['display current-configuration'],
            'display ont optical-info 0 all'         => ['display ont optical-info 0 all'],
            'display gpon olt state 0 1'             => ['display gpon olt state 0 1'],
            'display ont autofind all'               => ['display ont autofind all'],
            'display ip interface brief'             => ['display ip interface brief'],

            // ---- pager disable ----
            'screen-length 0 temporary'              => ['screen-length 0 temporary'],

            // ---- view navigation ----
            'enable'                                 => ['enable'],
            'config'                                 => ['config'],
            'quit'                                   => ['quit'],
            'return'                                 => ['return'],
            'interface gpon 0/1'                     => ['interface gpon 0/1'],
            'interface gpon 3/15'                    => ['interface gpon 3/15'],

            // ---- edge: extra internal whitespace normalized ----
            'display with extra spaces'              => ['display   ont   info   0   1   0'],
            'display with tabs'                      => ["display\tversion"],

            // ---- edge: leading/trailing whitespace stripped ----
            'display with padding'                   => ['  display version  '],

            // ---- edge: uppercase normalized to lowercase ----
            'DISPLAY uppercase'                      => ['DISPLAY VERSION'],
            'ENABLE uppercase'                       => ['ENABLE'],
            'QUIT uppercase'                         => ['QUIT'],
            'Interface gpon mixed case'              => ['Interface Gpon 0/1'],
        ];
    }

    // ── REJECT battery ───────────────────────────────────────────────────────

    /** @dataProvider rejectedCommands */
    public function test_rejects_non_whitelisted_commands(string $cmd): void
    {
        $this->expectException(ReadOnlyViolationException::class);
        $this->guard->assertAllowed($cmd);
    }

    public static function rejectedCommands(): array
    {
        return [
            // ---- destructive / config-modifying ----
            'undo'                                   => ['undo something'],
            'reset statistics'                       => ['reset statistics'],
            'reboot'                                 => ['reboot'],
            'ont add'                                => ['ont add 0 1 0 onttype 1'],
            'ont delete'                             => ['ont delete 0 1 0 0'],
            'ont modify'                             => ['ont modify 0 1 0 0 desc test'],
            'service-port'                           => ['service-port 100 vlan 200 gpon 0/1/0 ont 0'],
            'vlan'                                   => ['vlan 100 smart'],
            'save'                                   => ['save'],
            'write'                                  => ['write'],

            // ---- edge: display alone (no argument) ----
            'display alone'                          => ['display'],
            'display with only spaces'               => ['display   '],

            // ---- edge: uppercase destructive ----
            'REBOOT uppercase'                       => ['REBOOT'],
            'UNDO uppercase'                         => ['UNDO reset-statistics'],
            'SAVE uppercase'                         => ['SAVE'],

            // ---- edge: destructive with leading/extra whitespace ----
            'undo padded'                            => ['  undo   reset  '],
            'reboot padded'                          => ['  reboot  '],

            // ---- edge: non-GPON interface ----
            'interface eth'                          => ['interface eth 0/1'],
            'interface vlanif'                       => ['interface vlanif 100'],
            'interface loopback'                     => ['interface loopback 0'],

            // ---- edge: partial pager command ----
            'screen-length without temporary'        => ['screen-length 0'],
            'screen-length wrong value'              => ['screen-length 512 temporary'],
            'screen-length different suffix'         => ['screen-length 0 permanent'],

            // ---- edge: looks like display but isn't ----
            'displayx'                               => ['displayxyz version'],
            'dis play split'                         => ['dis play version'],
        ];
    }

    // ── Exception content ────────────────────────────────────────────────────

    public function test_exception_message_contains_normalized_command(): void
    {
        try {
            $this->guard->assertAllowed('  REBOOT  ');
            $this->fail('Expected ReadOnlyViolationException');
        } catch (ReadOnlyViolationException $e) {
            $this->assertStringContainsString('reboot', $e->getMessage());
        }
    }

    // ── Write allow-list ─────────────────────────────────────────────────────

    public function test_write_allowed_for_lab_sn(): void
    {
        // HWTCFEFCC9A2 is the only pre-authorized entry in WRITE_ALLOW_LIST.
        // Should NOT throw.
        $this->guard->assertWriteTargetAllowed('HWTCFEFCC9A2');
        $this->assertTrue(true);
    }

    public function test_write_allowed_sn_is_case_insensitive(): void
    {
        $this->guard->assertWriteTargetAllowed('hwtcfefcc9a2');
        $this->assertTrue(true);
    }

    public function test_write_denied_for_unknown_sn(): void
    {
        $this->expectException(WriteTargetDeniedException::class);
        $this->guard->assertWriteTargetAllowed('DEADBEEF00000000');
    }

    public function test_write_denied_message_contains_sn_and_allow_list(): void
    {
        try {
            $this->guard->assertWriteTargetAllowed('BAADBAAD00000000');
            $this->fail('Expected WriteTargetDeniedException');
        } catch (WriteTargetDeniedException $e) {
            $this->assertStringContainsString('BAADBAAD00000000', $e->getMessage());
            $this->assertStringContainsString('HWTCFEFCC9A2', $e->getMessage());
        }
    }

    // ── Logging ──────────────────────────────────────────────────────────────

    public function test_allowed_command_is_logged_at_info(): void
    {
        $spy = $this->makeLogSpy();
        (new ReadOnlyGuard($spy))->assertAllowed('display version');

        $this->assertCount(1, $spy->entries);
        $this->assertSame('info', $spy->entries[0]['level']);
        $this->assertSame('display version', $spy->entries[0]['context']['cmd']);
    }

    public function test_rejected_command_is_logged_at_warning(): void
    {
        $spy  = $this->makeLogSpy();
        $guard = new ReadOnlyGuard($spy);

        try {
            $guard->assertAllowed('reboot');
        } catch (ReadOnlyViolationException) {}

        $this->assertCount(1, $spy->entries);
        $this->assertSame('warning', $spy->entries[0]['level']);
    }

    public function test_write_allowed_sn_is_logged_at_notice(): void
    {
        $spy  = $this->makeLogSpy();
        (new ReadOnlyGuard($spy))->assertWriteTargetAllowed('HWTCFEFCC9A2');

        $this->assertCount(1, $spy->entries);
        $this->assertSame('notice', $spy->entries[0]['level']);
    }

    public function test_write_denied_sn_is_logged_at_error(): void
    {
        $spy  = $this->makeLogSpy();
        $guard = new ReadOnlyGuard($spy);

        try {
            $guard->assertWriteTargetAllowed('UNKNOWN000000000');
        } catch (WriteTargetDeniedException) {}

        $this->assertCount(1, $spy->entries);
        $this->assertSame('error', $spy->entries[0]['level']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeLogSpy(): object
    {
        return new class extends AbstractLogger {
            public array $entries = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->entries[] = compact('level', 'message', 'context');
            }
        };
    }
}
