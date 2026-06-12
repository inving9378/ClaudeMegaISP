<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\ReadOnlyViolationException;
use App\Services\OltDriver\Huawei\SessionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Pure unit test — no Laravel bootstrap, no DB, no network.
 *
 * FakeSession is defined at the bottom of this file. It simulates the
 * VRP Telnet/SSH session by returning pre-recorded responses keyed on
 * the prompt substring that the caller is waiting for.
 */
class HuaweiTransportTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeTransport(FakeSession $session, ?ReadOnlyGuard $guard = null): HuaweiTransport
    {
        return new HuaweiTransport(
            config:  ['host' => '10.50.11.2', 'connect_timeout' => 5],
            session: $session,
            guard:   $guard ?? new ReadOnlyGuard(new NullLogger()),
            logger:  new NullLogger(),
        );
    }

    private function openedSession(): FakeSession
    {
        $session = new FakeSession();
        // open() reads until PROMPT_USER, sends screen-length, reads again
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");
        return $session;
    }

    // ── open() ────────────────────────────────────────────────────────────────

    public function test_open_reads_user_prompt_and_disables_pager(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // screen-length 0 temporary must have been written
        $this->assertContains("screen-length 0 temporary\r\n", $session->written);
        $t->close();
    }

    public function test_open_sets_view_to_user(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();
        // Verify we can exec a command (only possible in user view)
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n"); // sync
        $session->queueResponse('MA5800-X7>', "display version output\r\nMA5800-X7>\r\n");

        $out = $t->exec('display version');
        $this->assertStringContainsString('display version output', $out);
        $t->close();
    }

    // ── exec() sync + echo strip ──────────────────────────────────────────────

    public function test_exec_strips_command_echo_from_output(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // sync read returns prompt, then real response includes the echo
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");
        $session->queueResponse('MA5800-X7>',
            "display board 0\r\n" .
            "  SlotID  BoardName\r\n" .
            "  0       H902GPHF\r\n" .
            "MA5800-X7>\r\n"
        );

        $out = $t->exec('display board 0');
        $this->assertStringNotContainsString('display board 0', $out);
        $this->assertStringContainsString('H902GPHF', $out);
        $t->close();
    }

    // ── exec() ---- More ---- handling ────────────────────────────────────────

    public function test_exec_handles_more_marker_and_continues(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // sync
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");

        // First chunk ends with ---- More ---- (no prompt yet)
        $session->queueResponse('MA5800-X7>',
            "display ont info 0 1 0 all\r\n" .
            "ONT 0 online\r\n" .
            "---- More ----"
        );

        // After SPACE sent, second chunk arrives with the rest + prompt
        $session->queueResponse('MA5800-X7>',
            "ONT 1 online\r\n" .
            "Total: 2\r\n" .
            "MA5800-X7>\r\n"
        );

        $out = $t->exec('display ont info 0 1 0 all');

        $this->assertStringNotContainsString('---- More ----', $out);
        $this->assertStringContainsString('ONT 0 online', $out);
        $this->assertStringContainsString('ONT 1 online', $out);
        $this->assertStringContainsString('Total: 2', $out);

        // Space must have been sent to advance the pager
        $this->assertContains(' ', $session->written);
        $t->close();
    }

    // ── exec() guard integration ───────────────────────────────────────────────

    public function test_exec_rejects_forbidden_command_before_writing(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $writesBefore = count($session->written);

        $this->expectException(ReadOnlyViolationException::class);
        $t->exec('reboot');

        // Nothing extra should have been written after the guard fired
        $this->assertCount($writesBefore, $session->written);
    }

    public function test_exec_rejects_undo_before_writing(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $this->expectException(ReadOnlyViolationException::class);
        $t->exec('undo something');
    }

    public function test_exec_rejects_save_before_writing(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $this->expectException(ReadOnlyViolationException::class);
        $t->exec('save');
    }

    // ── exec() before open() ──────────────────────────────────────────────────

    public function test_exec_throws_if_not_opened(): void
    {
        $session = new FakeSession();
        $t = $this->makeTransport($session);

        $this->expectException(\RuntimeException::class);
        $t->exec('display version');
    }

    // ── enterGponInterface() ──────────────────────────────────────────────────

    public function test_enter_gpon_interface_navigates_through_views(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // enable
        $session->queueResponse('MA5800-X7#', "MA5800-X7#\r\n");
        // config
        $session->queueResponse('MA5800-X7(config)#', "MA5800-X7(config)#\r\n");
        // interface gpon 0/1
        $session->queueResponse('MA5800-X7(config-if-gpon-', "MA5800-X7(config-if-gpon-0/1)#\r\n");

        $t->enterGponInterface(0, 1);

        $this->assertContains("enable\r\n",            $session->written);
        $this->assertContains("config\r\n",            $session->written);
        $this->assertContains("interface gpon 0/1\r\n", $session->written);
        $t->close();
    }

    // ── leaveToUserView() ─────────────────────────────────────────────────────

    public function test_leave_to_user_view_uses_return_from_interface(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // Simulate being in interface view by entering it
        $session->queueResponse('MA5800-X7#', "MA5800-X7#\r\n");
        $session->queueResponse('MA5800-X7(config)#', "MA5800-X7(config)#\r\n");
        $session->queueResponse('MA5800-X7(config-if-gpon-', "MA5800-X7(config-if-gpon-0/1)#\r\n");
        $t->enterGponInterface(0, 1);

        // leaveToUserView() now sends a single 'return' instead of chaining quit.
        // 'return' jumps directly to user-view from any view, avoiding the
        // "Are you sure to log out?" confirmation that 'quit' from enable triggers.
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");

        $t->leaveToUserView();

        $returnCount = count(array_filter($session->written, fn($w) => $w === "return\r\n"));
        $this->assertSame(1, $returnCount);
        $t->close();
    }

    // ── enterConfigView() ────────────────────────────────────────────────────

    public function test_enter_config_view_navigates_enable_then_config(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $session->queueResponse('MA5800-X7#',         "MA5800-X7#\r\n");
        $session->queueResponse('MA5800-X7(config)#', "MA5800-X7(config)#\r\n");

        $t->enterConfigView();

        $this->assertContains("enable\r\n", $session->written);
        $this->assertContains("config\r\n", $session->written);
        $t->close();
    }

    public function test_enter_config_view_from_interface_leaves_first(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // Navigate to interface first
        $session->queueResponse('MA5800-X7#',                "MA5800-X7#\r\n");
        $session->queueResponse('MA5800-X7(config)#',        "MA5800-X7(config)#\r\n");
        $session->queueResponse('MA5800-X7(config-if-gpon-', "MA5800-X7(config-if-gpon-0/1)#\r\n");
        $t->enterGponInterface(0, 1);

        // enterConfigView() from interface: return → user-view, then enable → config
        $session->queueResponse('MA5800-X7>',         "MA5800-X7>\r\n"); // return
        $session->queueResponse('MA5800-X7#',         "MA5800-X7#\r\n"); // enable
        $session->queueResponse('MA5800-X7(config)#', "MA5800-X7(config)#\r\n"); // config

        $t->enterConfigView();

        $returnCount = count(array_filter($session->written, fn($w) => $w === "return\r\n"));
        $this->assertGreaterThanOrEqual(1, $returnCount);
        $t->close();
    }

    public function test_enter_config_view_already_in_config_is_noop(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $session->queueResponse('MA5800-X7#',         "MA5800-X7#\r\n");
        $session->queueResponse('MA5800-X7(config)#', "MA5800-X7(config)#\r\n");
        $t->enterConfigView();

        $writtenBefore = count($session->written);

        // Calling enterConfigView again should be a no-op (already in config)
        $t->enterConfigView();

        $this->assertSame($writtenBefore, count($session->written));
        $t->close();
    }

    // ── exec() with custom timeout ────────────────────────────────────────────

    public function test_exec_accepts_custom_timeout(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        // exec() calls readUntil twice: once for sync-drain, once inside readFull.
        $session->queueResponse('MA5800-X7>', "MA5800-X7>\r\n");   // sync drain
        $session->queueResponse('MA5800-X7>', "display version\r\nVERSION : MA5800V100R018C00\r\nMA5800-X7>\r\n");

        // Should not throw; custom timeout is plumbed through readFull
        $output = $t->exec('display version', timeout: 120);
        $this->assertStringContainsString('VERSION', $output);
    }

    // ── close() ──────────────────────────────────────────────────────────────

    public function test_close_calls_session_close(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $t->close();

        $this->assertTrue($session->closed);
    }

    public function test_close_is_idempotent(): void
    {
        $session = $this->openedSession();
        $t = $this->makeTransport($session);
        $t->open();

        $t->close();
        $t->close(); // second call must not throw

        $this->assertTrue($session->closed);
    }

    public function test_close_before_open_does_not_throw(): void
    {
        $session = new FakeSession();
        $t = $this->makeTransport($session);

        $t->close(); // must not throw

        $this->assertFalse($session->closed);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// FakeSession — pre-recorded VRP responses for deterministic testing
// ─────────────────────────────────────────────────────────────────────────────

/**
 * In-memory SessionInterface implementation.
 *
 * Callers queue responses with queueResponse($waitingFor, $body).
 * readUntil() returns the first queued response whose $waitingFor substring
 * matches the $prompt argument, simulating the OLT's output stream.
 */
class FakeSession implements SessionInterface
{
    /** @var list<string> */
    public array $written = [];

    public bool $closed = false;

    /** @var list<array{prompt: string, body: string}> */
    private array $queue = [];

    public function queueResponse(string $prompt, string $body): void
    {
        $this->queue[] = ['prompt' => $prompt, 'body' => $body];
    }

    public function write(string $data): void
    {
        $this->written[] = $data;
    }

    public function readUntil(string $prompt, int $timeout = 30): string
    {
        foreach ($this->queue as $i => $entry) {
            if (str_contains($entry['prompt'], $prompt) || str_contains($prompt, $entry['prompt'])) {
                unset($this->queue[$i]);
                $this->queue = array_values($this->queue);
                return $entry['body'];
            }
        }

        // No matching response — return the prompt itself so callers don't hang
        return $prompt . "\r\n";
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
