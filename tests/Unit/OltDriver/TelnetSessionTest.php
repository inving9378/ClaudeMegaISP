<?php

namespace Tests\Unit\OltDriver;

use App\Services\OltDriver\Huawei\TelnetSession;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Overrides openSocket() so tests never open a real TCP connection.
 * The injected stream is one end of a local stream_socket_pair().
 */
class StubTelnetSession extends TelnetSession
{
    public function __construct(array $config, private readonly mixed $prebuilt)
    {
        parent::__construct($config);
    }

    protected function openSocket(): mixed
    {
        return $this->prebuilt;
    }
}

class TelnetSessionTest extends TestCase
{
    private const CONFIG = [
        'host'            => '10.0.0.1',
        'port'            => 23,
        'username'        => 'testuser',
        'password'        => 's3cr3t',
        'connect_timeout' => 5,
    ];

    /**
     * Returns [serverEnd, StubTelnetSession].
     * $serverEnd is the "OLT side" controlled by the test.
     *
     * @return array{0: resource, 1: StubTelnetSession}
     */
    private function makePair(): array
    {
        [$server, $client] = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
        stream_set_blocking($server, false);
        stream_set_timeout($client, 0, 50_000); // 50 ms read slice for speed

        return [$server, new StubTelnetSession(self::CONFIG, $client)];
    }

    // ── Constructor ───────────────────────────────────────────────────────────

    public function test_missing_host_throws(): void
    {
        $this->expectException(RuntimeException::class);
        new TelnetSession(['username' => 'u', 'password' => 'p']);
    }

    public function test_missing_username_throws(): void
    {
        $this->expectException(RuntimeException::class);
        new TelnetSession(['host' => '10.0.0.1', 'password' => 'p']);
    }

    public function test_missing_password_throws(): void
    {
        $this->expectException(RuntimeException::class);
        new TelnetSession(['host' => '10.0.0.1', 'username' => 'u']);
    }

    public function test_blank_host_throws(): void
    {
        $this->expectException(RuntimeException::class);
        new TelnetSession(['host' => '   ', 'username' => 'u', 'password' => 'p']);
    }

    // ── write() ───────────────────────────────────────────────────────────────

    public function test_write_delivers_bytes_to_remote(): void
    {
        [$server, $session] = $this->makePair();

        $session->write('AB');   // 2 chars × 20 ms = ~40 ms

        $received = '';
        $deadline = microtime(true) + 0.5;
        while (microtime(true) < $deadline && strlen($received) < 2) {
            $chunk = fread($server, 64);
            if ($chunk !== false && $chunk !== '') {
                $received .= $chunk;
            }
            usleep(5_000);
        }

        $this->assertSame('AB', $received);

        $session->close();
        fclose($server);
    }

    // ── readUntil() — basic ───────────────────────────────────────────────────

    public function test_readUntil_returns_when_prompt_found(): void
    {
        [$server, $session] = $this->makePair();
        fwrite($server, "Welcome\r\nMA5800-X7>\r\n");

        $result = $session->readUntil('MA5800-X7>', timeout: 2);

        $this->assertStringContainsString('MA5800-X7>', $result);

        $session->close();
        fclose($server);
    }

    public function test_readUntil_returns_partial_buffer_on_timeout(): void
    {
        [$server, $session] = $this->makePair();
        fwrite($server, 'partial without prompt');

        $result = $session->readUntil('NEVER_FOUND', timeout: 1);

        $this->assertStringContainsString('partial without prompt', $result);

        $session->close();
        fclose($server);
    }

    // ── readUntil() — transparent login ──────────────────────────────────────

    public function test_readUntil_handles_full_vrp_login_sequence(): void
    {
        [$server, $session] = $this->makePair();

        // Pre-load the complete VRP login exchange.
        // readUntil() will detect each prompt, strip it, send credentials,
        // and continue accumulating without needing fork/threads.
        fwrite($server, "Warning: Telnet not secure.\r\n\r\n>>User name:");
        fwrite($server, "\r\n>>User password:");
        fwrite($server, "\r\nMA5800-X7>\r\n");

        $result = $session->readUntil('MA5800-X7>', timeout: 5);

        $this->assertStringContainsString('MA5800-X7>', $result);

        // Drain what TelnetSession wrote back (credentials sent char-by-char)
        $sent = '';
        while (($ch = fread($server, 256)) !== false && $ch !== '') {
            $sent .= $ch;
        }
        $this->assertStringContainsString('testuser', $sent);
        $this->assertStringContainsString('s3cr3t', $sent);

        $session->close();
        fclose($server);
    }

    public function test_authenticated_flag_prevents_double_login_on_subsequent_reads(): void
    {
        [$server, $session] = $this->makePair();

        // First readUntil — triggers login path
        fwrite($server, ">>User name:");
        fwrite($server, ">>User password:");
        fwrite($server, "MA5800-X7>\r\n");
        $session->readUntil('MA5800-X7>', timeout: 5);

        // Second readUntil — must NOT try to login again even if 'User name:' appears in output
        fwrite($server, "Some output with User name: embedded\r\nMA5800-X7>\r\n");
        $result2 = $session->readUntil('MA5800-X7>', timeout: 2);

        $this->assertStringContainsString('MA5800-X7>', $result2);

        $session->close();
        fclose($server);
    }

    // ── readUntil() — VRP confirmation prompt ────────────────────────────────

    public function test_readUntil_auto_responds_to_cr_confirmation(): void
    {
        [$server, $session] = $this->makePair();

        // VRP sends confirmation prompt, then real output, then the command prompt
        fwrite($server, "{ <cr>||<K> }:\r\nRx ONU: -8.54 dBm\r\nMA5800-X7#\r\n");

        $result = $session->readUntil('MA5800-X7#', timeout: 2);

        $this->assertStringContainsString('Rx ONU: -8.54 dBm', $result);
        $this->assertStringContainsString('MA5800-X7#', $result);

        $session->close();
        fclose($server);
    }

    // ── readUntil() — More pager ──────────────────────────────────────────────

    public function test_readUntil_advances_through_more_pager(): void
    {
        [$server, $session] = $this->makePair();
        fwrite($server, "Page 1\r\n---- More ----\r\nPage 2\r\nMA5800-X7>\r\n");

        $result = $session->readUntil('MA5800-X7>', timeout: 2);

        $this->assertStringContainsString('Page 1', $result);
        $this->assertStringContainsString('Page 2', $result);
        $this->assertStringNotContainsString('---- More ----', $result);

        $session->close();
        fclose($server);
    }

    // ── close() ───────────────────────────────────────────────────────────────

    public function test_close_is_idempotent(): void
    {
        [$server, $session] = $this->makePair();

        $session->write('x');
        $session->close();
        $session->close(); // must not throw

        $this->assertTrue(true);
        fclose($server);
    }
}
