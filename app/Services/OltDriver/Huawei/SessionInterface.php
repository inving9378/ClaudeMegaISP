<?php

namespace App\Services\OltDriver\Huawei;

/**
 * Abstraction over the raw I/O channel to the OLT (SSH or Telnet).
 *
 * Keeping this interface separate allows HuaweiTransport to be tested
 * with a FakeSession without opening any real network connection.
 */
interface SessionInterface
{
    /**
     * Send bytes to the OLT exactly as given (no guard applied here).
     * The guard runs one layer up in HuaweiTransport::exec().
     */
    public function write(string $data): void;

    /**
     * Read from the OLT until $prompt appears in the accumulated buffer
     * or $timeout seconds elapse.
     *
     * Returns everything received up to and including the first occurrence
     * of $prompt.
     *
     * @throws \RuntimeException on timeout or connection loss
     */
    public function readUntil(string $prompt, int $timeout = 30): string;

    /**
     * Close the connection gracefully (no VRP quit — that is HuaweiTransport's job).
     */
    public function close(): void;
}
