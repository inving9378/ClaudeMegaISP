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

    // ── Write allow-list ──────────────────────────────────────────────────────
    //
    // Rules (non-negotiable for write operations):
    //   1. SN must be in WRITE_ALLOW_LIST — default answer is NO.
    //   2. Operations are per-ONT only (ont reset, ont delete, ont add, service-port
    //      scoped to one ONT). Board/port/system-level commands are never generated.
    //   3. Every allowed write is logged at 'notice' level with the SN for audit.

    private const WRITE_ALLOW_LIST = ['HWTCFEFCC9A2'];

    /**
     * Assert that $sn is authorized for write operations.
     *
     * @throws WriteTargetDeniedException if $sn is not in WRITE_ALLOW_LIST
     */
    public function assertWriteTargetAllowed(string $sn): void
    {
        $normalized = strtoupper(trim($sn));

        if (! in_array($normalized, self::WRITE_ALLOW_LIST, strict: true)) {
            $this->logger->error('[olt-huawei] guard:write-denied', [
                'sn'         => $normalized,
                'allow_list' => self::WRITE_ALLOW_LIST,
            ]);

            throw new WriteTargetDeniedException($normalized, self::WRITE_ALLOW_LIST);
        }

        $this->logger->notice('[olt-huawei] guard:write-allowed', ['sn' => $normalized]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function normalize(string $command): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($command)));
    }
}
