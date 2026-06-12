<?php

namespace App\Services\OltDriver\Huawei;

use App\Services\OltDriver\OltDriverInterface;
use App\Services\OltDriver\Huawei\Parsers\AutofindParser;
use App\Services\OltDriver\Huawei\Parsers\BoardParser;
use App\Services\OltDriver\Huawei\Parsers\OntInfoParser;
use App\Services\OltDriver\Huawei\Parsers\OntListParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalBatchParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use App\Services\OltDriver\Huawei\Parsers\VersionParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Read-only driver for Huawei MA5800/MA5600 OLTs.
 *
 * Implements OltDriverInterface using a persistent SSH/Telnet session via
 * HuaweiTransport. All responses are normalized to the same shape returned
 * by SmartOltDriver so that the rest of the system is driver-agnostic.
 *
 * Pre-condition: the injected $transport must already be open (open() called
 * by the factory/container before constructing this driver).
 *
 * unique_external_id format: "{olt_id}:{frame}/{slot}/{port}:{ont_id}"
 * Example: "1:0/3/2:0" → OLT 1, frame 0, slot 3, port 2, ont-id 0.
 *
 * signal_1490 = ONT Rx power (downstream, 1490 nm — what the customer receives)
 * signal_1310 = OLT Rx power (upstream,   1310 nm — what the OLT receives from the ONT)
 */
class HuaweiDriver implements OltDriverInterface
{
    private readonly string $oltId;
    private readonly int    $frame;

    public function __construct(
        private readonly HuaweiTransport $transport,
        private readonly array           $config,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->oltId = (string) ($config['olt_id'] ?? 'huawei-olt');
        $this->frame = (int)   ($config['frame']  ?? 0);
    }

    // ── OltDriverInterface ────────────────────────────────────────────────────

    public function getName(): string
    {
        return 'Huawei';
    }

    // ── Lecturas globales ─────────────────────────────────────────────────────

    public function listOlts(): array
    {
        try {
            $ver = VersionParser::parse($this->transport->exec('display version'));
            return [
                'success' => true,
                'data'    => [[
                    'id'       => $this->oltId,
                    'name'     => $this->config['name'] ?? ($ver['model'] ?: 'Huawei OLT'),
                    'model'    => $ver['model'],
                    'firmware' => $ver['firmware'],
                    'patch'    => $ver['patch'],
                    'uptime'   => $ver['uptime'],
                ]],
            ];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * Returns DBA profiles from `display dba-profile all`.
     * Normalized to SmartOLT shape {id, name, speed, direction, type}.
     * DBA profiles control upstream bandwidth; direction='both' is an approximation.
     */
    public function listSpeedProfiles(): array
    {
        try {
            $output   = $this->transport->exec('display dba-profile all');
            $profiles = $this->parseDbaProfiles($output);
            return ['success' => true, 'response' => $profiles];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    // ── Lecturas bulk ─────────────────────────────────────────────────────────

    /**
     * Returns ONTs detected but not yet authorized.
     */
    public function getUnconfiguredOnus(?string $oltId = null): array
    {
        if (! $this->handlesOlt($oltId)) {
            return ['success' => false, 'message' => "OLT '{$oltId}' is not managed by this driver."];
        }

        try {
            $output  = $this->transport->exec('display ont autofind all');
            $found   = AutofindParser::parse($output, $this->logger);
            $response = array_map(fn($e) => [
                'sn'           => $e['sn'],
                'olt_id'       => $this->oltId,
                'port'         => $e['port'],
                'vender_id'    => $e['vender_id'],
                'equipment_id' => $e['equipment_id'],
                'autofind_time'=> $e['autofind_time'],
            ], $found);
            return ['success' => true, 'response' => $response];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * Full ONU inventory for this OLT.
     *
     * Iterates every GPON board (from BoardParser) × its ports.
     * For each slot, enters `interface gpon {frame}/{slot}` and runs
     * `display ont info {port} all` (interface-context syntax).
     * The user-view slash syntax `display ont info {f}/{s}/{p} all` is NOT used
     * because VRP's space-eater bug consumes the space before `all`, producing
     * `display ont info 0/3/2all → % Parameter error`.
     *
     * For MA5800-X7 with H902GPHF(×2, 8p) + H901GPHF(×3, 16p): 64 display calls.
     * All calls share one persistent session — no reconnect overhead.
     */
    public function getOnusByOlt(string $oltId): array
    {
        if (! $this->handlesOlt($oltId)) {
            return ['success' => false, 'message' => "OLT '{$oltId}' is not managed by this driver."];
        }

        try {
            $onus = $this->collectAllOnts();
            return ['success' => true, 'onus' => $onus];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * @pending-validation — optical info requires session against the live OLT.
     * For each GPON board: enters interface gpon {frame}/{slot}, runs
     * `display ont optical-info {port} all`, leaves.
     */
    public function getOnusSignals(?string $oltId = null): array
    {
        if (! $this->handlesOlt($oltId)) {
            return ['success' => false, 'message' => "OLT '{$oltId}' is not managed by this driver."];
        }

        try {
            $response = $this->collectAllSignals();
            return ['success' => true, 'response' => $response];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function getOnusStatus(?string $oltId = null): array
    {
        if (! $this->handlesOlt($oltId)) {
            return ['success' => false, 'message' => "OLT '{$oltId}' is not managed by this driver."];
        }

        try {
            $onus     = $this->collectAllOnts();
            $response = array_map(fn($onu) => [
                'sn'                 => $onu['sn'],
                'olt_id'             => $onu['olt_id'],
                'unique_external_id' => $onu['unique_external_id'],
                'board'              => $onu['board'],
                'port'               => $onu['port'],
                'onu'                => $onu['onu'],
                'zone_id'            => null,
                'status'             => $onu['status'],
            ], $onus);
            return ['success' => true, 'response' => $response];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    // ── Lecturas individuales ─────────────────────────────────────────────────

    public function getOnuDetails(string $onuId): array
    {
        try {
            [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);
            $output = $this->transport->exec("display ont info {$frame} {$slot} {$port} {$ontId}");
            $onts   = OntInfoParser::parse($output, $this->logger);

            if (empty($onts)) {
                return ['success' => false, 'message' => "ONT not found: {$onuId}"];
            }

            $ont     = $onts[0];
            $optical = $this->fetchOpticalForOnt($frame, $slot, $port, $ontId);

            return [
                'success'     => true,
                'onu_details' => array_merge(
                    $this->buildOnuShape($ont, $this->oltId, $frame, $slot, $port),
                    [
                        'olt_name'      => $this->config['name'] ?? 'Huawei OLT',
                        'signal'        => $this->categorizeSignal($optical['rx_onu'] ?? null),
                        'signal_1310'   => $optical['rx_olt'] !== null ? (string) $optical['rx_olt'] : '-',
                        'signal_1490'   => $optical['rx_onu'] !== null ? (string) $optical['rx_onu'] : '-',
                        'mode'          => 'N/A',
                        'wan_mode'      => 'N/A',
                        'vlan'          => null,
                        'service_ports' => null,
                        'ethernet_ports'=> null,
                        'wifi_ports'    => null,
                        'voip_ports'    => null,
                        'last_up_time'  => $ont['last_up_time'],
                        'last_down_time'=> $ont['last_down_time'],
                        'last_down_cause'=>$ont['last_down_cause'],
                        'distance_m'    => $ont['distance_m'],
                        'description'   => $ont['description'],
                    ]
                ),
            ];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    /**
     * @pending-validation — signal values require live OLT validation.
     * Ground truth: HWTCFEFCC9A2 (0/3/2:0) → Rx ONU -8.54 / Rx OLT -13.04 dBm.
     */
    public function getOnuSignal(string $onuId): array
    {
        try {
            [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);
            $optical = $this->fetchOpticalForOnt($frame, $slot, $port, $ontId);

            $rxOnu = $optical['rx_onu'] ?? null;
            $rxOlt = $optical['rx_olt'] ?? null;

            return [
                'success'         => true,
                'onu_signal'      => $this->categorizeSignal($rxOnu),
                'onu_signal_1310' => $rxOlt !== null ? (string) $rxOlt : '-',
                'onu_signal_1490' => $rxOnu !== null ? (string) $rxOnu : '-',
            ];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    public function getOnuStatus(string $onuId): array
    {
        try {
            [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);
            $output = $this->transport->exec("display ont info {$frame} {$slot} {$port} {$ontId}");
            $onts   = OntInfoParser::parse($output, $this->logger);

            if (empty($onts)) {
                return ['success' => false, 'message' => "ONT not found: {$onuId}"];
            }

            $ont = $onts[0];
            return [
                'success'            => true,
                'onu_status'         => $this->normalizeStatus($ont['run_state']),
                'last_status_change' => $ont['last_up_time'] ?? $ont['last_down_time'],
            ];
        } catch (Throwable $e) {
            return $this->error($e);
        }
    }

    // ── Lecturas adicionales (no en OltDriverInterface — ver roadmap B3) ────────

    /**
     * Look up a single ONT by serial number.
     *
     * Uses `display ont info by-sn {sn}` (config-view command) — returns the
     * full detail block for the matching ONT including its F/S/P location.
     *
     * NOT part of OltDriverInterface. Promotion to the interface is deferred
     * to B3 when the UI defines whether cross-driver SN lookup is required
     * (see roadmap item "B3-decision-findOnuBySn").
     *
     * @return array{success:bool, onu?:array, message?:string}
     */
    public function findOnuBySn(string $sn): array
    {
        try {
            $this->transport->enterConfigView();
            $output = $this->transport->exec("display ont info by-sn {$sn}");

            $onts = OntInfoParser::parse($output, $this->logger);

            if (empty($onts)) {
                return ['success' => false, 'message' => "ONT with SN '{$sn}' not found"];
            }

            $ont = $onts[0];
            [$frame, $slot, $port] = $this->extractFsp($output);

            return [
                'success' => true,
                'onu'     => $this->buildOnuShape($ont, $this->oltId, $frame, $slot, $port),
            ];
        } catch (Throwable $e) {
            return $this->error($e);
        } finally {
            try {
                $this->transport->leaveToUserView();
            } catch (Throwable) {
                // best-effort
            }
        }
    }

    // ── Escrituras — NOT IMPLEMENTED (B4) ────────────────────────────────────

    public function authorizeOnu(array $data): array
    {
        throw new WriteNotEnabledException('authorizeOnu');
    }

    public function deauthorizeOnu(string $onuId): array
    {
        throw new WriteNotEnabledException('deauthorizeOnu');
    }

    public function setOnuEnabled(string $onuId, bool $enabled): array
    {
        throw new WriteNotEnabledException('setOnuEnabled');
    }

    public function rebootOnu(string $onuId): array
    {
        throw new WriteNotEnabledException('rebootOnu');
    }

    public function setOnuSpeedProfile(string $onuId, array $data): array
    {
        throw new WriteNotEnabledException('setOnuSpeedProfile');
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function collectAllOnts(): array
    {
        $boards = BoardParser::parse($this->transport->exec('display board 0'));
        $onus   = [];

        foreach ($boards as $board) {
            if (! $board['is_gpon']) {
                continue;
            }

            $slot      = $board['slot'];
            $portCount = $board['port_count'];

            try {
                // Enter interface-context once per slot (avoids space-eater bug in user-view)
                $this->transport->enterGponInterface($this->frame, $slot);

                for ($port = 0; $port < $portCount; $port++) {
                    try {
                        // Interface-context syntax: "display ont info {port} all"
                        $output  = $this->transport->exec("display ont info {$port} all");
                        $portOnt = OntListParser::parse($output, $this->logger);

                        foreach ($portOnt as $ont) {
                            $onus[] = $this->buildOnuShape($ont, $this->oltId, $this->frame, $slot, $port);
                        }
                    } catch (Throwable $e) {
                        $this->logger->warning('[olt-huawei] driver:collectAllOnts:port-error', [
                            'slot' => $slot, 'port' => $port, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('[olt-huawei] driver:collectAllOnts:slot-error', [
                    'slot' => $slot, 'error' => $e->getMessage(),
                ]);
            } finally {
                try {
                    $this->transport->leaveToUserView();
                } catch (Throwable) {
                    // best-effort
                }
            }
        }

        return $onus;
    }

    /**
     * @pending-validation
     */
    private function collectAllSignals(): array
    {
        $boards   = BoardParser::parse($this->transport->exec('display board 0'));
        $response = [];

        foreach ($boards as $board) {
            if (! $board['is_gpon']) {
                continue;
            }

            $slot      = $board['slot'];
            $portCount = $board['port_count'];

            try {
                // enterGponInterface($frame, $slot): generates "interface gpon {frame}/{slot}"
                $this->transport->enterGponInterface($this->frame, $slot);

                for ($port = 0; $port < $portCount; $port++) {
                    try {
                        $optOutput = $this->transport->exec("display ont optical-info {$port} all");
                        $optItems  = OpticalBatchParser::parse($optOutput, $this->logger);

                        foreach ($optItems as $item) {
                            $externalId = "{$this->oltId}:{$this->frame}/{$slot}/{$port}:{$item['ont_id']}";
                            $rxOnu      = $item['rx_onu'];
                            $rxOlt      = $item['rx_olt'];

                            $response[] = [
                                'sn'                 => '',  // SN not in optical output; caller may enrich
                                'olt_id'             => $this->oltId,
                                'unique_external_id' => $externalId,
                                'board'              => (string) $slot,
                                'port'               => (string) $port,
                                'onu'                => $item['ont_id'],
                                'zone_id'            => null,
                                'signal'             => $this->categorizeSignal($rxOnu),
                                'signal_1310'        => $rxOlt !== null ? (string) $rxOlt : '-',
                                'signal_1490'        => $rxOnu !== null ? (string) $rxOnu : '-',
                            ];
                        }
                    } catch (Throwable $e) {
                        $this->logger->warning('[olt-huawei] driver:collectAllSignals:port-error', [
                            'slot' => $slot, 'port' => $port, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->warning('[olt-huawei] driver:collectAllSignals:slot-error', [
                    'slot' => $slot, 'error' => $e->getMessage(),
                ]);
            } finally {
                try {
                    $this->transport->leaveToUserView();
                } catch (Throwable) {
                    // best-effort
                }
            }
        }

        return $response;
    }

    /**
     * Fetches optical info for a single ONT.
     * Enters interface gpon {frame}/{slot}, runs display ont optical-info {port} {ont_id},
     * then returns to user view.
     *
     * @pending-validation
     * @return array{rx_onu:float|null, tx_onu:float|null, rx_olt:float|null, ...}
     */
    private function fetchOpticalForOnt(int $frame, int $slot, int $port, int $ontId): array
    {
        try {
            $this->transport->enterGponInterface($frame, $slot);
            $output  = $this->transport->exec("display ont optical-info {$port} {$ontId}");
            $entries = OpticalInfoParser::parse($output, $this->logger);
            return $entries[0] ?? ['rx_onu' => null, 'tx_onu' => null, 'rx_olt' => null];
        } catch (Throwable $e) {
            $this->logger->warning('[olt-huawei] driver:fetchOpticalForOnt:error', [
                'slot' => $slot, 'port' => $port, 'ont_id' => $ontId, 'error' => $e->getMessage(),
            ]);
            return ['rx_onu' => null, 'tx_onu' => null, 'rx_olt' => null];
        } finally {
            try {
                $this->transport->leaveToUserView();
            } catch (Throwable) {
                // best-effort
            }
        }
    }

    private function buildOnuShape(array $ont, string $oltId, int $frame, int $slot, int $port): array
    {
        return [
            'sn'                 => $this->normalizeSn($ont['sn']),
            'unique_external_id' => "{$oltId}:{$frame}/{$slot}/{$port}:{$ont['ont_id']}",
            'olt_id'             => $oltId,
            'board'              => (string) $slot,
            'port'               => (string) $port,
            'onu'                => (int) $ont['ont_id'],
            'status'             => $this->normalizeStatus($ont['run_state']),
            'signal'             => '-',
            'signal_1310'        => '-',
            'signal_1490'        => '-',
        ];
    }

    /**
     * Map Huawei run-state → SmartOLT status string.
     */
    private function normalizeStatus(string $state): string
    {
        return match (strtolower($state)) {
            'online'      => 'Online',
            'los'         => 'LOS',
            'power fail', 'power-fail', 'powerfail' => 'Power fail',
            default       => 'Offline',
        };
    }

    /**
     * Map ONT Rx power (downstream, 1490 nm) to SmartOLT signal category.
     *
     * Thresholds based on Meganet operational ranges:
     *   >= -8  dBm : Critical (sobrepotencia — too high)
     *   >= -18 dBm : Warning  (aviso)
     *   >  -27 dBm : Very good
     *   <= -27 dBm : Critical (baja señal — too low)
     */
    private function categorizeSignal(?float $rxDbm): string
    {
        if ($rxDbm === null) {
            return '-';
        }
        if ($rxDbm >= -8.0) {
            return 'Critical';
        }
        if ($rxDbm >= -18.0) {
            return 'Warning';
        }
        if ($rxDbm > -27.0) {
            return 'Very good';
        }
        return 'Critical';
    }

    /**
     * Parse unique_external_id → [frame, slot, port, ont_id].
     * Format: "{olt_id}:{frame}/{slot}/{port}:{ont_id}"
     */
    private function parseOnuId(string $onuId): array
    {
        $parts = explode(':', $onuId);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException("Invalid onuId format (expected olt:f/s/p:id): '{$onuId}'");
        }

        $fsp = explode('/', $parts[1]);
        if (count($fsp) !== 3) {
            throw new \InvalidArgumentException("Invalid F/S/P in onuId: '{$onuId}'");
        }

        return [(int) $fsp[0], (int) $fsp[1], (int) $fsp[2], (int) $parts[2]];
    }

    private function parseDbaProfiles(string $output): array
    {
        $profiles = [];

        foreach (explode("\n", $output) as $line) {
            $line = rtrim($line);

            if ($line === '' || str_starts_with(ltrim($line), '#')
                || str_starts_with(ltrim($line), '-')
                || str_starts_with(ltrim($line), 'Profile-ID')
                || str_starts_with(ltrim($line), 'Total')
            ) {
                continue;
            }

            $parts = preg_split('/\s{2,}/', trim($line));
            if (count($parts) >= 4 && ctype_digit((string) $parts[0])) {
                $maxKbps    = (int) $parts[3];
                $profiles[] = [
                    'id'        => $parts[0],
                    'name'      => $parts[1],
                    'speed'     => (string) $maxKbps,
                    'direction' => 'both',
                    'type'      => $parts[4] ?? 'DBA',
                ];
            }
        }

        return $profiles;
    }

    /**
     * Normalize a Huawei SN to the human-readable decoded form.
     *
     * VRP produces three SN representations depending on the command:
     *   • Tabular batch:  "48575443FEFCC9A2"             (16-char hex)
     *   • Per-ONT detail: "HWTCFEFCC9A2"                 (decoded, 12 chars)
     *   • by-sn output:   "48575443FEFCC9A2 (HWTC-FEFCC9A2)" (hex + parenthetical)
     *
     * All three are normalized to the decoded form ("HWTCFEFCC9A2") so that
     * unique_external_id and the SN field are consistent across commands.
     *
     * Decoding: strip any parenthetical annotation, then decode the first 4 bytes
     * of the hex form as ASCII vendor chars; leave unchanged if not printable ASCII.
     */
    private function normalizeSn(string $sn): string
    {
        // Strip VRP vendor annotation: "48575443FEFCC9A2 (HWTC-FEFCC9A2)" → "48575443FEFCC9A2"
        $sn = trim((string) preg_replace('/\s*\(.*\)\s*$/', '', $sn));

        if (strlen($sn) !== 16 || !ctype_xdigit($sn)) {
            return $sn;  // already decoded (HWTCFEFCC9A2) or unknown format
        }

        $vendor = pack('H*', substr($sn, 0, 8));
        if (preg_match('/^[\x20-\x7E]{4}$/', $vendor)) {
            return $vendor . substr($sn, 8);
        }
        return $sn;
    }

    /**
     * Extract F/S/P (frame/slot/port) from a VRP detail output block.
     * Used by findOnuBySn to locate the ONT in the OLT topology.
     */
    private function extractFsp(string $output): array
    {
        if (preg_match('/F\/S\/P\s*:\s*(\d+)\/(\d+)\/(\d+)/', $output, $m)) {
            return [(int) $m[1], (int) $m[2], (int) $m[3]];
        }
        $this->logger->warning('[olt-huawei] driver:extractFsp:not-found-in-output');
        return [$this->frame, 0, 0];
    }

    private function handlesOlt(?string $oltId): bool
    {
        return $oltId === null || $oltId === $this->oltId;
    }

    private function error(Throwable $e): array
    {
        $this->logger->error('[olt-huawei] driver:error', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
