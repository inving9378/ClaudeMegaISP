<?php

namespace App\Services\OltDriver\Huawei;

use RuntimeException;

/**
 * Concrete SessionInterface over a raw Telnet TCP socket.
 *
 * Key VRP lessons baked in:
 *   1. Login is handled transparently inside readUntil() — the caller never
 *      sees the >>User name: / >>User password: exchange.
 *   2. write() sends data byte-by-byte with a 20 ms inter-byte delay.
 *      VRP drops characters when bytes arrive too fast (space-eater bug).
 *   3. readUntil() automatically responds to { <cr>||<K> } confirmation
 *      prompts and ---- More ---- pager pages.
 *   4. The TCP connection is lazy — established on the first read or write.
 *   5. close() resets state so the instance is safe to reuse after
 *      HuaweiTransport::close() + a fresh open().
 */
class TelnetSession implements SessionInterface
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private int    $connectTimeout;

    /** @var resource|null */
    private mixed $socket = null;

    private bool   $authenticated = false;
    private string $rawLog        = '';     // accumulates every byte received; access via getRawLog()
    private bool   $enableRawLog  = false;

    /** Enable byte-level logging of everything received (for B1c diagnostics). */
    public function enableRawLog(): void { $this->enableRawLog = true; }

    /** Return everything received since the session was opened (or last reset). */
    public function getRawLog(): string  { return $this->rawLog; }

    public function __construct(array $config)
    {
        $host     = trim((string) ($config['host']     ?? ''));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password']      ?? '');

        if ($host === '' || $username === '' || $password === '') {
            throw new RuntimeException(
                'TelnetSession: host, username and password are required in config'
            );
        }

        $this->host           = $host;
        $this->port           = (int) ($config['port']            ?? 23);
        $this->username       = $username;
        $this->password       = $password;
        $this->connectTimeout = (int) ($config['connect_timeout'] ?? 30);
    }

    // ── SessionInterface ──────────────────────────────────────────────────────

    public function write(string $data): void
    {
        $this->ensureConnected();
        $this->writeSlow($data);
    }

    public function readUntil(string $prompt, int $timeout = 30): string
    {
        $this->ensureConnected();

        $buf      = '';
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $chunk = fread($this->socket, 4096);
            if ($chunk !== false && $chunk !== '') {
                if ($this->enableRawLog) {
                    $this->rawLog .= $chunk;
                }
                $buf .= $chunk;
            }

            // ── Transparent VRP login (lessons 1 + 2) ────────────────────
            if (!$this->authenticated) {
                if (($pos = strpos($buf, 'User name:')) !== false) {
                    $this->writeSlow($this->username . "\r\n");
                    $buf = substr($buf, $pos + strlen('User name:'));
                    continue;
                }
                if (($pos = strpos($buf, 'User password:')) !== false) {
                    $this->writeSlow($this->password . "\r\n");
                    $buf = substr($buf, $pos + strlen('User password:'));
                    continue;
                }
                if (($pos = strpos($buf, 'Password:')) !== false) {
                    $this->writeSlow($this->password . "\r\n");
                    $buf = substr($buf, $pos + strlen('Password:'));
                    continue;
                }
            }

            // ── VRP confirmation prompt — auto-respond (lesson 3) ─────────
            if (str_contains($buf, '{ <cr>')) {
                fwrite($this->socket, "\r\n");
                $buf = (string) preg_replace('/\{\s*<cr>[^}]*\}\s*:?\s*/', '', $buf);
                continue;
            }

            // ── Strip ANSI/VT100 sequences before any string checks ───────
            // VRP emits cursor-movement codes (e.g. ESC[37D) after clearing the
            // pager line; they corrupt str_contains prompt and More detection.
            $buf = (string) preg_replace('/\x1B\[[0-9;]*[A-Za-z]|\x1B[A-Za-z]/', '', $buf);
            $buf = str_replace(["\x0F", "\x0E"], '', $buf);

            // ── Pager — covers both short and full More forms (lesson 3) ──
            // MA5800 VRP V100R018 sends "---- More ( Press 'Q' to break ) ----"
            // while older fixtures use "---- More ----". Match on common prefix.
            if (str_contains($buf, '---- More')) {
                fwrite($this->socket, ' ');
                $buf = (string) preg_replace('/---- More[^\r\n]*/u', '', $buf);
                continue;
            }

            // ── Target prompt found ───────────────────────────────────────
            if (str_contains($buf, $prompt)) {
                if (!$this->authenticated) {
                    $this->authenticated = true;
                }
                return $buf;
            }

            if ($chunk === false || $chunk === '') {
                usleep(5_000); // 5 ms — avoid busy-spin when socket is idle
            }
        }

        // Return partial buffer on timeout; HuaweiTransport decides how to handle it
        return $buf;
    }

    public function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket        = null;
            $this->authenticated = false;
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function ensureConnected(): void
    {
        if ($this->socket !== null) {
            return;
        }
        $this->socket = $this->openSocket();
    }

    /**
     * Override in tests by subclassing and returning a pre-built stream,
     * keeping unit tests completely off the network.
     */
    protected function openSocket(): mixed
    {
        $errCode = 0;
        $errMsg  = '';

        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errCode,
            $errMsg,
            (float) $this->connectTimeout
        );

        if ($socket === false) {
            throw new RuntimeException(
                "TelnetSession: cannot connect to {$this->host}:{$this->port} — {$errMsg} ({$errCode})"
            );
        }

        // 100 ms read slice — tight loop without burning CPU
        stream_set_timeout($socket, 0, 100_000);

        return $socket;
    }

    /** Send data byte-by-byte with 20 ms inter-byte delay (VRP space-eater fix). */
    private function writeSlow(string $data): void
    {
        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            fwrite($this->socket, $data[$i]);
            usleep(20_000);
        }
    }
}
