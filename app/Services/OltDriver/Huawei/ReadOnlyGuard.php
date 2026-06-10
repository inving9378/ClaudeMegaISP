<?php

namespace App\Services\OltDriver\Huawei;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Whitelist gate for every command sent to a Huawei OLT.
 *
 * Rule: if a command is not explicitly permitted, it is rejected.
 * There is no blacklist — the default answer is NO.
 *
 * Constructed with a PSR-3 logger so the class has zero framework
 * dependencies and is testable with a plain PHPUnit\Framework\TestCase.
 * In production, inject the 'olt-huawei' channel logger via the container.
 */
class ReadOnlyGuard
{
    /**
     * Ordered list of patterns that mark a command as safe.
     * Evaluated against the normalized (lowercase, single-spaced) command.
     */
    private const ALLOWED = [
        // Any display command with at least one argument
        '/^display\s+\S.*/i',

        // Disable the VRP pager for the current session (the only non-display
        // exception; it is purely cosmetic and does not modify config)
        '/^screen-length 0 temporary$/i',

        // View-navigation commands — needed to reach interface context for
        // optical-info and other display commands only available inside a view
        '/^enable$/i',
        '/^config$/i',
        '/^quit$/i',
        '/^return$/i',

        // Enter a GPON OLT interface view (read inside it, never write)
        '/^interface gpon \d+\/\d+$/i',
    ];

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Assert that $command is safe to send to the OLT.
     *
     * Normalizes the input (trim + collapse whitespace + lowercase) before
     * matching so that extra spaces or accidental uppercase cannot bypass
     * the whitelist.
     *
     * @throws ReadOnlyViolationException if the command is not whitelisted
     */
    public function assertAllowed(string $command): void
    {
        $normalized = $this->normalize($command);

        foreach (self::ALLOWED as $pattern) {
            if (preg_match($pattern, $normalized)) {
                $this->logger->info('[olt-huawei] guard:allowed', [
                    'cmd' => $normalized,
                ]);
                return;
            }
        }

        $this->logger->warning('[olt-huawei] guard:rejected', [
            'cmd'      => $normalized,
            'original' => $command,
        ]);

        throw new ReadOnlyViolationException($normalized);
    }

    // ── Write scaffolding (NOT active) ───────────────────────────────────────
    //
    // When write operations are introduced (B1b-3+), this guard will require:
    //   1. The target ONT SN must be in an explicit allow-list.
    //      The test ONT (HWTCFEFCC9A2) is the only pre-authorized entry.
    //   2. Commands will NEVER operate at port/board/system level;
    //      only per-ONT primitives (ont modify, service-port on a single ONT).
    //   3. Each write command will be logged at 'notice' level with the SN,
    //      timestamp, and originating user before being dispatched.
    //
    // The stub below always throws so that accidental write paths surface
    // immediately rather than silently reaching the OLT.

    /**
     * Future write guard — always throws until writes are implemented.
     *
     * @throws ReadOnlyViolationException always
     */
    public function assertWriteTargetAllowed(string $sn): never
    {
        $this->logger->error('[olt-huawei] guard:write-blocked', ['sn' => $sn]);

        throw new ReadOnlyViolationException(
            "Writes not implemented. Pre-authorized test ONT: HWTCFEFCC9A2. Attempted SN: {$sn}"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalize(string $command): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($command)));
    }
}
