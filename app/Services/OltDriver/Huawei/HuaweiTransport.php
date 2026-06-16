<?php

namespace App\Services\OltDriver\Huawei;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Manages a single, persistent session against a Huawei MA5800/MA5600 OLT.
 *
 * Responsibilities
 * ────────────────
 * • open()  — connect, synchronize to the user-view prompt, disable the pager.
 * • exec()  — guard → sync → send → read (handling ---- More ----) → strip echo.
 * • enterGponInterface() / leaveToUserView() — navigate VRP views safely.
 * • close() — quit cleanly so the OLT anti-attack timer is not triggered.
 *
 * One instance = one session. Do NOT instantiate a new transport per command;
 * that is exactly the pattern that trips the OLT's connection-rate ACL.
 *
 * Anti-cooldown policy (open with back-off)
 * ──────────────────────────────────────────
 * open() accepts a $maxAttempts argument and sleeps with exponential back-off
 * between tries (2 s → 8 s → 30 s cap). Never hammer the OLT in a tight loop.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Credentials are read from the injected $config array or from env/config at
 * construction time — never hardcoded. Suggested keys:
 *   host, port, username, password, transport ('ssh'|'telnet'), connect_timeout
 * ─────────────────────────────────────────────────────────────────────────────
 */
class HuaweiTransport
{
    // VRP prompt surfaces after login and after each command
    private const PROMPT_USER    = 'MA5800-X7>';
    private const PROMPT_ENABLE  = 'MA5800-X7#';
    private const PROMPT_CONFIG  = 'MA5800-X7(config)#';
    // Interface prompt varies by slot/port, matched as a substring
    private const PROMPT_IF_PREFIX = 'MA5800-X7(config-if-gpon-';

    // Both More forms seen in the wild on MA5800 VRP V100R018.
    // We match on the common prefix so a single check covers both.
    private const MORE_MARKER      = '---- More ----';
    private const MORE_MARKER_FULL = "---- More ( Press 'Q' to break ) ----";
    private const MORE_PREFIX      = '---- More';

    /** Back-off ladder in seconds (last value is the cap) */
    private const BACKOFF = [2, 8, 30];

    private bool $opened = false;

    /** Current view: 'user' | 'enable' | 'config' | 'interface' */
    private string $view = 'user';

    public function __construct(
        private readonly array           $config,
        private readonly SessionInterface $session,
        private readonly ReadOnlyGuard   $guard  = new ReadOnlyGuard(),
        private readonly LoggerInterface  $logger = new NullLogger(),
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Open the session and disable the VRP pager.
     *
     * @param int $maxAttempts Retries with exponential back-off (default: 3)
     * @throws RuntimeException after all attempts fail
     */
    public function open(int $maxAttempts = 3): void
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $this->doOpen();
                return;
            } catch (RuntimeException $e) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $delay = self::BACKOFF[min($attempt - 1, count(self::BACKOFF) - 1)];
                $this->logger->warning('[olt-huawei] transport:open-retry', [
                    'attempt' => $attempt,
                    'delay_s' => $delay,
                    'error'   => $e->getMessage(),
                ]);
                sleep($delay);
            }
        }
    }

    /**
     * Execute a single read-only command and return the trimmed output.
     *
     * Steps: guard → sync to current prompt → write → read until prompt
     * (handling ---- More ---- pages) → strip command echo → return output.
     *
     * @throws ReadOnlyViolationException if $command fails the whitelist
     * @throws RuntimeException           on session loss
     *
     * @param int $timeout Maximum seconds to wait for the complete response.
     *                     Increase for commands with many More pages (e.g. `display ont info 2 all`
     *                     on a heavily-populated port may paginate for >60 s).
     */
    public function exec(string $command, int $timeout = 60): string
    {
        $this->assertOpen();
        $this->guard->assertAllowed($command);  // guard BEFORE any I/O

        $prompt = $this->currentPrompt();

        // Sync: drain any buffered output from the previous command
        $this->session->readUntil($prompt, timeout: 10);

        // Send the command
        $this->session->write($command . "\r\n");
        $this->logger->info('[olt-huawei] transport:exec', ['cmd' => $command, 'timeout' => $timeout]);

        // Read response, flushing ---- More ---- pages
        $raw = $this->readFull($prompt, timeout: $timeout);

        return $this->stripEcho($command, $raw);
    }

    /**
     * Navigate to configuration view.
     * Path (from wherever we are): user → enable → config.
     *
     * Use this before exec()-ing commands that are available in config-view
     * but not in user-view (e.g. `display board`, `display ont autofind all`,
     * `display ont info by-sn`).
     */
    public function enterConfigView(): void
    {
        $this->assertOpen();

        if ($this->view === 'interface') {
            $this->leaveToUserView();
        }

        if ($this->view === 'user') {
            $this->runNavigation('enable', 'enable');
        }

        if ($this->view === 'enable') {
            $this->runNavigation('config', 'config');
        }
    }

    /**
     * Navigate into a GPON OLT interface view.
     * Path: user → enable → config → interface gpon {slot}/{port}
     */
    public function enterGponInterface(int $slot, int $port): void
    {
        $this->assertOpen();

        if ($this->view === 'interface') {
            $this->leaveToUserView();
        }

        if ($this->view === 'user') {
            $this->runNavigation('enable', 'enable');
        }

        if ($this->view === 'enable') {
            $this->runNavigation('config', 'config');
        }

        if ($this->view === 'config') {
            $this->runNavigation("interface gpon {$slot}/{$port}", 'interface');
        }
    }

    /**
     * Return to user view from anywhere using the VRP `return` command.
     *
     * `return` jumps directly to user-view from any config/enable/interface
     * view without confirmation prompts.  Chaining `quit` commands is avoided
     * because `quit` from enable-view emits "Are you sure to log out? (y/n)"
     * which disrupts subsequent commands in the same Telnet session.
     */
    public function leaveToUserView(): void
    {
        $this->assertOpen();

        if ($this->view === 'user') {
            return;
        }

        $this->runNavigation('return', 'user');
    }

    public function isOpen(): bool
    {
        return $this->opened;
    }

    /**
     * Send a WRITE command to the OLT, bypassing ReadOnlyGuard::assertAllowed().
     *
     * Called exclusively by HuaweiDriver::executeSteps() for non-navigation
     * write steps (ont desc, ont reset, ont add, service-port, etc.).
     * The caller is responsible for guard checks (assertWriteTargetAllowed) before
     * calling this method.
     *
     * Confirmation handling: if $confirmReply is non-null, the method reads until
     * the VRP (y/n) prompt, sends the reply, then reads until the command prompt.
     * If the OLT firmware skips the confirmation and goes straight to the command
     * prompt, readUntil('(y/n)') will time out — ⚠️ VERIFY against live hardware.
     */
    public function execWrite(string $cmd, ?string $confirmReply = null): string
    {
        $this->assertOpen();
        $prompt = $this->currentPrompt();

        // Drain any buffered output from the previous command.
        // After multi-step navigation (e.g. user→enable→config→interface), leftover
        // TCP bytes from partial prompt frames can leave the session in an intermediate
        // state. The drain waits up to 10 s; we then pause 300 ms so VRP fully settles
        // its echo buffer before the write bytes arrive — otherwise spaces in the command
        // arguments are silently dropped (space-eater bug on interface-view write path).
        $this->session->readUntil($prompt, timeout: 10);
        usleep(300_000); // 300 ms — VRP echo-buffer settle (space-eater guard)

        // Send without guard check — this is intentional for write operations
        $this->session->write($cmd . "\r\n");
        $this->logger->notice('[olt-huawei] transport:exec-write', ['cmd' => $cmd]);

        if ($confirmReply !== null) {
            // VRP emits (y/n) before the command prompt; read until we see it
            $partial = $this->session->readUntil('(y/n)', timeout: 15);
            $this->session->write($confirmReply . "\r\n");
            $rest = $this->readFull($prompt, timeout: 30);
            return trim($partial . ' ' . $rest);
        }

        $raw = $this->readFull($prompt, timeout: 30);
        return $this->stripEcho($cmd, $raw);
    }

    /**
     * Close the session with a clean VRP quit sequence.
     */
    public function close(): void
    {
        if (! $this->opened) {
            return;
        }

        try {
            $this->leaveToUserView();
            $this->session->write("quit\r\n");
        } catch (\Throwable) {
            // Best-effort; the socket close below handles the rest
        } finally {
            $this->session->close();
            $this->opened = false;
            $this->view   = 'user';
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function doOpen(): void
    {
        // The session implementation (SSH/Telnet) handles TCP + auth.
        // After auth, VRP shows the user-view prompt.
        $this->session->readUntil(self::PROMPT_USER, timeout: $this->config['connect_timeout'] ?? 30);

        $this->opened = true;
        $this->view   = 'user';

        // Disable pager — guard is bypassed here because this is an internal
        // housekeeping step, not a caller-supplied command.
        $this->session->write("screen-length 0 temporary\r\n");
        $this->session->readUntil(self::PROMPT_USER, timeout: 10);

        $this->logger->info('[olt-huawei] transport:opened', [
            'host' => $this->config['host'] ?? '?',
        ]);
    }

    /**
     * Read the full command response, transparently consuming ---- More ---- pages.
     */
    private function readFull(string $prompt, int $timeout = 60): string
    {
        $buffer   = '';
        $deadline = time() + $timeout;

        while (time() < $deadline) {
            $chunk   = $this->session->readUntil($prompt, timeout: 5);
            $buffer .= $chunk;

            // Strip ANSI escape sequences (color codes, cursor-movement sequences)
            // that VRP sends after advancing the pager — they break str_contains.
            $buffer = $this->stripAnsi($buffer);

            // Arrived at the prompt — done
            if (str_contains($buffer, $prompt)) {
                break;
            }

            // More pager — covers both "---- More ----" and "---- More ( Press 'Q' to break ) ----"
            if (str_contains($buffer, self::MORE_PREFIX)) {
                $buffer = (string) preg_replace('/---- More[^\r\n]*/u', '', $buffer);
                $this->session->write(' ');
            }
        }

        return $buffer;
    }

    /**
     * Remove the command echo that VRP prepends to its response.
     *
     * VRP echoes the exact command on the first line (possibly with a
     * trailing space). We match by normalized equality — not str_contains —
     * so that content lines that happen to mention the command keyword are
     * not mistakenly stripped.
     */
    private function stripEcho(string $command, string $raw): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $raw));

        // Strip first line only if it is the echo of our command
        if (isset($lines[0])) {
            $echo = strtolower(preg_replace('/\s+/', ' ', trim($lines[0])));
            $cmd  = strtolower(preg_replace('/\s+/', ' ', trim($command)));
            if ($echo === $cmd) {
                array_shift($lines);
            }
        }

        // Drop trailing empty lines and prompt lines (fix: explicit while body,
        // no operator-precedence ambiguity between && and ||)
        while ($lines) {
            $last = trim((string) end($lines));
            if ($last === '' || str_contains($last, '>') || str_contains($last, '#')) {
                array_pop($lines);
            } else {
                break;
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Run a navigation command (enable/config/quit/interface) without the
     * caller-facing guard (navigation commands are guard-whitelisted but we
     * call guard explicitly here to be explicit).
     */
    private function runNavigation(string $command, string $newView): void
    {
        $this->guard->assertAllowed($command);
        $this->session->write($command . "\r\n");
        $this->session->readUntil($this->promptForView($newView), timeout: 10);
        $this->view = $newView;

        $this->logger->debug('[olt-huawei] transport:navigate', [
            'cmd'  => $command,
            'view' => $newView,
        ]);
    }

    private function currentPrompt(): string
    {
        return $this->promptForView($this->view);
    }

    private function promptForView(string $view): string
    {
        return match ($view) {
            'enable'    => self::PROMPT_ENABLE,
            'config'    => self::PROMPT_CONFIG,
            'interface' => self::PROMPT_IF_PREFIX,  // partial match is fine for readUntil
            default     => self::PROMPT_USER,
        };
    }

    private function viewAfterQuit(): string
    {
        return match ($this->view) {
            'interface' => 'config',
            'config'    => 'enable',
            'enable'    => 'user',
            default     => 'user',
        };
    }

    private function assertOpen(): void
    {
        if (! $this->opened) {
            throw new RuntimeException('HuaweiTransport: session is not open. Call open() first.');
        }
    }

    /**
     * Remove ANSI/VT100 escape sequences from a buffer.
     *
     * VRP sends cursor-movement codes (e.g. ESC[37D) after clearing a pager
     * line. If left in the buffer they break str_contains prompt checks.
     *
     * Patterns stripped:
     *   ESC [ <params> <final>   — CSI sequences (colors, cursor movement)
     *   ESC <single-char>        — 2-char ESC sequences
     *   \x0F / \x0E              — SI/SO charset shifts (sometimes emitted by VRP)
     */
    private function stripAnsi(string $buf): string
    {
        // CSI sequences: ESC [ ... letter
        $buf = (string) preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $buf);
        // Other 2-char ESC sequences
        $buf = (string) preg_replace('/\x1B[A-Za-z]/', '', $buf);
        // SI / SO
        return str_replace(["\x0F", "\x0E"], '', $buf);
    }
}
