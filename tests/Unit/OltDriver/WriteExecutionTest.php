<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\HuaweiCommandBuilder;
use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\SessionInterface;
use App\Services\OltDriver\Huawei\WriteExecutionAbortedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

// ─────────────────────────────────────────────────────────────────────────────
// Minimal SessionInterface fake — queues raw responses in order.
// Used to test HuaweiTransport::execWrite() at the transport level.
// ─────────────────────────────────────────────────────────────────────────────
class FakeOltSession implements SessionInterface
{
    public array  $writes = [];
    private array $queue  = [];

    public function prime(string $response): self
    {
        $this->queue[] = $response;
        return $this;
    }

    public function write(string $data): void
    {
        $this->writes[] = $data;
    }

    /**
     * Return queued responses in order, regardless of $prompt.
     * readFull() calls this in a loop; the response must contain $prompt
     * for readFull() to break out — callers must include the prompt in the string.
     */
    public function readUntil(string $prompt, int $timeout = 30): string
    {
        return $this->queue ? array_shift($this->queue) : ($prompt);
    }

    public function close(): void {}

    public function wroteLine(string $line): bool
    {
        return in_array($line . "\r\n", $this->writes, strict: true);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// WriteExecutionTest
//
// Two sections:
//   A) Transport-level: FakeOltSession → HuaweiTransport::execWrite()
//   B) Driver-level:    mock HuaweiTransport → HuaweiDriver public methods
// ─────────────────────────────────────────────────────────────────────────────
class WriteExecutionTest extends TestCase
{
    // ── Fixtures ──────────────────────────────────────────────────────────────

    private static string $ontPortFixture;
    private static string $ontPortFixtureNonAllowed;

    public static function setUpBeforeClass(): void
    {
        $base  = __DIR__ . '/fixtures/huawei/';
        self::$ontPortFixture = file_get_contents($base . 'display_ont_info_port.txt');
        // Same fixture but SN replaced with a non-allow-listed value
        self::$ontPortFixtureNonAllowed = str_replace(
            'HWTCFEFCC9A2',
            'DEADBEEF0000',
            self::$ontPortFixture
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a HuaweiTransport backed by a FakeOltSession already "opened"
     * in interface view (simulates being inside `interface gpon 0/3`).
     */
    private function makeTransport(FakeOltSession $session): HuaweiTransport
    {
        $transport = new HuaweiTransport(
            config:  ['host' => '10.0.0.1', 'username' => 'u', 'password' => 'p'],
            session: $session,
            guard:   new ReadOnlyGuard(),
            logger:  new NullLogger(),
        );
        // Force opened + interface view so we can call execWrite() directly
        $ref = new \ReflectionObject($transport);

        $propOpen = $ref->getProperty('opened');
        $propOpen->setAccessible(true);
        $propOpen->setValue($transport, true);

        $propView = $ref->getProperty('view');
        $propView->setAccessible(true);
        $propView->setValue($transport, 'interface');

        return $transport;
    }

    // Prompt string that readFull() looks for when in interface view
    private const IF_PROMPT = 'MA5800-X7(config-if-gpon-0/3)#';

    /**
     * Build a HuaweiDriver with dry_run=false and a mocked transport.
     * The transport mock returns $ontFixture for exec() (ONT info lookup).
     */
    private function makeDriver(HuaweiTransport $transport, bool $dryRun = false): HuaweiDriver
    {
        return new HuaweiDriver(
            transport: $transport,
            config:    ['olt_id' => '1', 'frame' => 0, 'dry_run' => $dryRun],
            logger:    new NullLogger(),
        );
    }

    /**
     * Build a mocked transport that:
     * - isOpen() → true (bypasses Cache::lock in withSession)
     * - exec()   → returns $ontFixture (for the display ont info lookup in withWriteTarget)
     */
    private function mockTransport(string $ontFixture = ''): HuaweiTransport
    {
        $fixture = $ontFixture ?: self::$ontPortFixture;

        $mock = $this->createMock(HuaweiTransport::class);
        $mock->method('isOpen')->willReturn(true);
        $mock->method('exec')->willReturn($fixture);

        return $mock;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SECTION A — Transport level: execWrite() via FakeOltSession
    // ═════════════════════════════════════════════════════════════════════════

    public function test_exec_write_sends_command_bytes(): void
    {
        $session = new FakeOltSession();
        $session
            ->prime(self::IF_PROMPT)                         // sync drain
            ->prime("  Success.\r\n" . self::IF_PROMPT);    // command response

        $transport = $this->makeTransport($session);
        $transport->execWrite('ont modify 2 0 desc PRUEBA-W3', null);

        $this->assertTrue($session->wroteLine('ont modify 2 0 desc PRUEBA-W3'));
    }

    public function test_exec_write_no_confirmation_when_confirm_null(): void
    {
        $session = new FakeOltSession();
        $session
            ->prime(self::IF_PROMPT)
            ->prime("  Success.\r\n" . self::IF_PROMPT);

        $transport = $this->makeTransport($session);
        $transport->execWrite('ont modify 2 0 desc PRUEBA', null);

        // Only the command line was sent — no (y/n) reply
        $this->assertFalse($session->wroteLine('y'), 'No confirmation reply should be sent when confirm=null');
        $this->assertCount(1, $session->writes);
    }

    public function test_exec_write_sends_confirmation_reply_when_prompted(): void
    {
        $session = new FakeOltSession();
        $session
            ->prime(self::IF_PROMPT)                                   // sync drain
            ->prime('Are you sure to reset ONT? (y/n)[n]:')           // VRP confirmation prompt
            ->prime("  The operation succeeded.\r\n" . self::IF_PROMPT); // post-confirm response

        $transport = $this->makeTransport($session);
        $transport->execWrite('ont reset 2 0', 'y');

        $this->assertTrue($session->wroteLine('ont reset 2 0'), 'Command must be sent');
        $this->assertTrue($session->wroteLine('y'), 'Confirmation reply must be sent');
    }

    public function test_exec_write_confirmation_reply_sent_after_command(): void
    {
        $session = new FakeOltSession();
        $session
            ->prime(self::IF_PROMPT)
            ->prime('(y/n)[n]:')
            ->prime("OK\r\n" . self::IF_PROMPT);

        $transport = $this->makeTransport($session);
        $transport->execWrite('ont reset 2 0', 'y');

        // Order: cmd first, then reply
        $cmdIdx = array_search("ont reset 2 0\r\n", $session->writes, true);
        $repIdx = array_search("y\r\n", $session->writes, true);
        $this->assertNotFalse($cmdIdx);
        $this->assertNotFalse($repIdx);
        $this->assertGreaterThan($cmdIdx, $repIdx, 'Confirmation reply must come after the command');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SECTION B — Driver level: executeSteps() via public methods
    // ═════════════════════════════════════════════════════════════════════════

    // ── B-1: Happy path ───────────────────────────────────────────────────────

    public function test_desc_ont_live_returns_success(): void
    {
        $transport = $this->mockTransport();
        $transport->expects($this->once())
            ->method('enterGponInterface')
            ->with(0, 3);
        $transport->expects($this->once())
            ->method('execWrite')
            ->with('ont modify 2 0 desc PRUEBA-W3', null)
            ->willReturn('  Success.');
        $transport->expects($this->once())
            ->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['dry_run']);
        $this->assertSame('HWTCFEFCC9A2', $result['sn']);
    }

    public function test_desc_ont_live_transcript_has_three_entries(): void
    {
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        $transport->method('execWrite')->willReturn('Success.');
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        // nav(interface) + write(desc) + nav(return) = 3 entries
        $this->assertCount(3, $result['transcript']);
    }

    public function test_desc_ont_transcript_write_step_has_response(): void
    {
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        $transport->method('execWrite')->willReturn('  Success.');
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $writeStep = $result['transcript'][1]; // middle step is the write
        $this->assertSame('ont modify 2 0 desc PRUEBA-W3', $writeStep['cmd']);
        $this->assertSame('  Success.', $writeStep['response']);
    }

    // ── B-2: Abort-on-error ───────────────────────────────────────────────────

    public function test_abort_on_error_returns_failure(): void
    {
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        $transport->method('execWrite')
            ->willReturn('Error: The ONT does not exist.');
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('aborted_at', $result);
        $this->assertSame('ont modify 2 0 desc PRUEBA-W3', $result['aborted_at']);
    }

    public function test_abort_stops_before_next_write_step(): void
    {
        // Use rebootOnu which has a write step (ont reset) followed by a return nav
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        // ont reset fails — the return nav must NOT be reached via execWrite
        $transport->expects($this->once())
            ->method('execWrite')
            ->willReturn('Failure: Parameter error.');
        // leaveToUserView is a nav method; after abort, executeSteps stops
        // and the nav step is never processed (the loop throws before reaching return)
        $transport->expects($this->never())->method('leaveToUserView');

        $result = $this->makeDriver($transport)->rebootOnu('1:0/3/2:0');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failure', $result['message']);
    }

    public function test_abort_exception_carries_failed_cmd_and_raw_response(): void
    {
        $raw = 'Error: The parameter is out of range.';
        $e   = new WriteExecutionAbortedException('ont modify 2 0 desc BAD', $raw, []);

        $this->assertSame('ont modify 2 0 desc BAD', $e->failedCmd);
        $this->assertSame($raw, $e->rawResponse);
        $this->assertStringContainsString($raw, $e->getMessage());
    }

    public function test_abort_on_failure_keyword(): void
    {
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        $transport->method('execWrite')->willReturn('  Failure: Device busy.');
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'X');

        $this->assertFalse($result['success']);
    }

    public function test_abort_on_failed_keyword(): void
    {
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        // VRP emits "failed" at the start of a line (e.g. after entering wrong context)
        $transport->method('execWrite')->willReturn("failed to allocate resource.");
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'X');

        $this->assertFalse($result['success']);
    }

    public function test_abort_on_vrp_percent_prefix(): void
    {
        // VRP returns "% Unknown command" for unrecognized commands (e.g. space-eater mangling)
        $transport = $this->mockTransport();
        $transport->method('enterGponInterface');
        $transport->method('execWrite')->willReturn('% Unknown command, the error locates at \'^\'');
        $transport->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'X');

        $this->assertFalse($result['success']);
    }

    // ── B-3: Guard ────────────────────────────────────────────────────────────

    public function test_guard_denied_returns_failure_for_non_allowed_sn(): void
    {
        $transport = $this->mockTransport(self::$ontPortFixtureNonAllowed);
        $transport->expects($this->never())->method('enterGponInterface');
        $transport->expects($this->never())->method('execWrite');
        $transport->expects($this->never())->method('leaveToUserView');

        $result = $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('DEADBEEF0000', $result['message']);
    }

    public function test_guard_denied_no_write_bytes_sent(): void
    {
        // guard rejects before execWrite() — already proved by never() above,
        // this test names the intent explicitly for readability
        $transport = $this->mockTransport(self::$ontPortFixtureNonAllowed);
        $transport->expects($this->never())->method('execWrite');

        $this->makeDriver($transport)->descOnt('1:0/3/2:0', 'PRUEBA-W3');
    }

    public function test_guard_also_blocks_reboot_for_non_allowed_sn(): void
    {
        $transport = $this->mockTransport(self::$ontPortFixtureNonAllowed);
        $transport->expects($this->never())->method('execWrite');

        $result = $this->makeDriver($transport)->rebootOnu('1:0/3/2:0');

        $this->assertFalse($result['success']);
    }

    // ── B-4: Dry-run intacto ──────────────────────────────────────────────────

    public function test_dry_run_returns_dry_run_flag(): void
    {
        $transport = $this->mockTransport();
        $transport->expects($this->never())->method('execWrite');
        $transport->expects($this->never())->method('enterGponInterface');
        $transport->expects($this->never())->method('leaveToUserView');

        // In dry_run mode, withWriteTarget calls transport.exec() for ont info lookup
        // but never calls execWrite/enterGponInterface/leaveToUserView
        $result = $this->makeDriver($transport, dryRun: true)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $this->assertTrue($result['dry_run']);
        $this->assertTrue($result['success']);
    }

    public function test_dry_run_contains_expected_commands(): void
    {
        $transport = $this->mockTransport();

        $result = $this->makeDriver($transport, dryRun: true)->descOnt('1:0/3/2:0', 'PRUEBA-W3');

        $this->assertContains('interface gpon 0/3', $result['commands']);
        $this->assertContains('ont modify 2 0 desc PRUEBA-W3', $result['commands']);
        $this->assertContains('return', $result['commands']);
    }

    public function test_dry_run_set_onu_enabled_contains_deactivate(): void
    {
        $transport = $this->mockTransport();
        $transport->expects($this->never())->method('execWrite');

        $result = $this->makeDriver($transport, dryRun: true)->setOnuEnabled('1:0/3/2:0', false);

        $this->assertTrue($result['dry_run']);
        $this->assertContains('ont deactivate 2 0', $result['commands']);
    }

    // ── B-5: ontDesc builder inverse ──────────────────────────────────────────

    public function test_ont_desc_builder_with_text(): void
    {
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, 'PRUEBA-W3');

        $this->assertSame('interface gpon 0/3', $steps[0]['cmd']);
        $this->assertSame('ont modify 2 0 desc PRUEBA-W3', $steps[1]['cmd']);
        $this->assertFalse($steps[1]['nav']);
        $this->assertNull($steps[1]['confirm']);
        $this->assertSame('return', $steps[2]['cmd']);
    }

    public function test_ont_desc_builder_empty_generates_undo(): void
    {
        // Empty desc → "ont modify port ontid desc" (no value = clears the field in VRP V100R018)
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, '');

        $this->assertSame('ont modify 2 0 desc', $steps[1]['cmd']);
    }

    public function test_ont_desc_builder_sanitizes_quotes(): void
    {
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, 'PRUEBA"EVIL');

        $this->assertStringNotContainsString('"', $steps[1]['cmd']);
        $this->assertStringContainsString('PRUEBAEVIL', $steps[1]['cmd']);
    }

    public function test_ont_desc_builder_step_shape(): void
    {
        $steps = HuaweiCommandBuilder::ontDesc(0, 3, 2, 0, 'X');

        foreach ($steps as $step) {
            $this->assertArrayHasKey('cmd',     $step);
            $this->assertArrayHasKey('nav',     $step);
            $this->assertArrayHasKey('confirm', $step);
        }
    }
}
