<?php

namespace App\Services\OltDriver\Huawei;

use App\Services\OltDriver\OltDriverInterface;
use App\Services\OltDriver\Huawei\Parsers\AutofindParser;
use App\Services\OltDriver\Huawei\Parsers\BoardParser;
use App\Services\OltDriver\Huawei\Parsers\OntInfoParser;
use App\Services\OltDriver\Huawei\Parsers\OntListParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalBatchParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use App\Services\OltDriver\Huawei\Parsers\ProfileListParser;
use App\Services\OltDriver\Huawei\Parsers\VersionParser;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Driver for Huawei MA5800/MA5600 OLTs.
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
 *
 * Write operations (B4):
 *   dry_run = true  (default) — commands are generated but never sent; returns
 *                               ['dry_run' => true, 'commands' => [...VRP strings...]].
 *   dry_run = false           — W3: commands are executed against the live OLT.
 *   Allow-list enforced by ReadOnlyGuard::assertWriteTargetAllowed().
 */
class HuaweiDriver implements OltDriverInterface
{
    private readonly string        $oltId;
    private readonly int           $frame;
    private readonly bool          $dryRun;
    private readonly ReadOnlyGuard $guard;

    public function __construct(
        private readonly HuaweiTransport $transport,
        private readonly array           $config,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?ReadOnlyGuard                   $guard  = null,
    ) {
        $this->oltId  = (string) ($config['olt_id']  ?? 'huawei-olt');
        $this->frame  = (int)   ($config['frame']   ?? 0);
        $this->dryRun = (bool)  ($config['dry_run'] ?? true);   // safe default: always dry-run
        $this->guard  = $guard ?? new ReadOnlyGuard($this->logger);
    }

    // ── OltDriverInterface ────────────────────────────────────────────────────

    public function getName(): string
    {
        return 'Huawei';
    }

    // ── Lecturas globales ─────────────────────────────────────────────────────

    public function listOlts(): array
    {
        return $this->withSession(function () {
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
        });
    }

    /**
     * Returns DBA profiles from `display dba-profile all`.
     * Normalized to SmartOLT shape {id, name, speed, direction, type}.
     * DBA profiles control upstream bandwidth; direction='both' is an approximation.
     */
    public function listSpeedProfiles(): array
    {
        return $this->withSession(function () {
            try {
                $output   = $this->transport->exec('display dba-profile all');
                $profiles = $this->parseDbaProfiles($output);
                return ['success' => true, 'response' => $profiles];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
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

        return $this->withSession(function () {
            try {
                $output   = $this->transport->exec('display ont autofind all');
                $found    = AutofindParser::parse($output, $this->logger);
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
        });
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
        return $this->withSession(function () use ($onuId) {
            try {
                [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);
                // Interface-context syntax (same pattern as withWriteTarget + collectAllOnts).
                $this->transport->enterGponInterface($frame, $slot);
                $output = $this->transport->exec("display ont info {$port} {$ontId}");
                $this->transport->leaveToUserView();
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
                            'olt_name'       => $this->config['name'] ?? 'Huawei OLT',
                            'signal'         => $this->categorizeSignal($optical['rx_onu'] ?? null),
                            'signal_1310'    => $optical['rx_olt'] !== null ? (string) $optical['rx_olt'] : '-',
                            'signal_1490'    => $optical['rx_onu'] !== null ? (string) $optical['rx_onu'] : '-',
                            'mode'           => 'N/A',
                            'wan_mode'       => 'N/A',
                            'vlan'           => null,
                            'service_ports'  => null,
                            'ethernet_ports' => null,
                            'wifi_ports'     => null,
                            'voip_ports'     => null,
                            'last_up_time'   => $ont['last_up_time'],
                            'last_down_time' => $ont['last_down_time'],
                            'last_down_cause'=> $ont['last_down_cause'],
                            'distance_m'     => $ont['distance_m'],
                            'description'    => $ont['description'],
                        ]
                    ),
                ];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
    }

    /**
     * @pending-validation — signal values require live OLT validation.
     * Ground truth: HWTCFEFCC9A2 (0/3/2:0) → Rx ONU -8.54 / Rx OLT -13.04 dBm.
     */
    public function getOnuSignal(string $onuId): array
    {
        return $this->withSession(function () use ($onuId) {
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
        });
    }

    public function getOnuStatus(string $onuId): array
    {
        return $this->withSession(function () use ($onuId) {
            try {
                [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);
                // Interface-context syntax (same pattern as withWriteTarget + collectAllOnts).
                $this->transport->enterGponInterface($frame, $slot);
                $output = $this->transport->exec("display ont info {$port} {$ontId}");
                $this->transport->leaveToUserView();
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
        });
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
        return $this->withSession(function () use ($sn) {
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
        });
    }

    // ── Lecturas de perfiles (W2-1 pre-checks) ───────────────────────────────

    /**
     * Returns all ONT line-profiles from `display ont-lineprofile gpon all`.
     *
     * @return array{success:bool, profiles?:list<array{id:int,name:string,bindings:int}>, message?:string}
     */
    public function listLineProfiles(): array
    {
        return $this->withSession(function () {
            try {
                $output   = $this->transport->exec('display ont-lineprofile gpon all');
                $profiles = ProfileListParser::parse($output);
                return ['success' => true, 'profiles' => $profiles];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
    }

    /**
     * Returns all ONT service-profiles from `display ont-srvprofile gpon all`.
     *
     * @return array{success:bool, profiles?:list<array{id:int,name:string,bindings:int}>, message?:string}
     */
    public function listSrvProfiles(): array
    {
        return $this->withSession(function () {
            try {
                $output   = $this->transport->exec('display ont-srvprofile gpon all');
                $profiles = ProfileListParser::parse($output);
                return ['success' => true, 'profiles' => $profiles];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
    }

    // ── Gestión de sesión (uso exclusivo del cron) ────────────────────────────

    /**
     * Open the Telnet session. Called ONCE by the cron before any bulk read.
     * The cron holds the lock externally (TTL 900s); web calls must NOT call this directly.
     */
    public function openSession(int $maxAttempts = 2): void
    {
        $this->transport->open($maxAttempts);
    }

    public function closeSession(): void
    {
        $this->transport->close();
    }

    // ── Escrituras — B4 (dry_run = true por defecto) ─────────────────────────

    /**
     * Reinicia un ONT remotamente.
     *
     * VRP: `ont reset <port> <ont-id>` (inside interface gpon {frame}/{slot})
     * Dry-run: returns the command sequence without sending anything.
     * Live (dry_run=false): executes via executeSteps().
     */
    public function rebootOnu(string $onuId): array
    {
        return $this->withWriteTarget($onuId, function (int $frame, int $slot, int $port, int $ontId, string $sn) {
            $steps = HuaweiCommandBuilder::rebootOnt($frame, $slot, $port, $ontId);

            if ($this->dryRun) {
                return $this->dryRunResult($sn, $steps);
            }

            return $this->executeSteps($sn, $steps);
        });
    }

    /**
     * Elimina/desautoriza un ONT de la OLT.
     *
     * ⚠️ Pausar el sync de SmartOLT de esta OLT antes de ejecutar.
     * Usar en el ciclo: deauthorizeOnu() → authorizeOnu() para re-provisionar.
     */
    public function deauthorizeOnu(string $onuId): array
    {
        return $this->withWriteTarget($onuId, function (int $frame, int $slot, int $port, int $ontId, string $sn) {
            $steps = HuaweiCommandBuilder::deleteOnt($frame, $slot, $port, $ontId);

            if ($this->dryRun) {
                return $this->dryRunResult($sn, $steps);
            }

            return $this->executeSteps($sn, $steps);
        });
    }

    /**
     * Habilita o deshabilita el servicio de un ONT (activate / deactivate).
     * No elimina la config del ONT — solo suspende/reactiva la sesión OMCI.
     */
    public function setOnuEnabled(string $onuId, bool $enabled): array
    {
        return $this->withWriteTarget($onuId, function (int $frame, int $slot, int $port, int $ontId, string $sn) use ($enabled) {
            $steps = $enabled
                ? HuaweiCommandBuilder::activateOnt($frame, $slot, $port, $ontId)
                : HuaweiCommandBuilder::deactivateOnt($frame, $slot, $port, $ontId);

            if ($this->dryRun) {
                return $this->dryRunResult($sn, $steps);
            }

            return $this->executeSteps($sn, $steps);
        });
    }

    /**
     * Cambia (o borra) la descripción cosmética de un ONT.
     *
     * Es el write más inofensivo disponible: no toca VLANs ni perfiles, no interrumpe
     * el servicio y no requiere `save` para ser observable por lectura.
     * Inverso: descOnt($onuId, '') → genera `undo ont desc` y elimina la etiqueta.
     *
     * Primer write aprobado para W3 (solo contra SN HWTCFEFCC9A2).
     */
    public function descOnt(string $onuId, string $desc): array
    {
        return $this->withWriteTarget($onuId, function (int $frame, int $slot, int $port, int $ontId, string $sn) use ($desc) {
            $steps = HuaweiCommandBuilder::ontDesc($frame, $slot, $port, $ontId, $desc);

            if ($this->dryRun) {
                return $this->dryRunResult($sn, $steps);
            }

            return $this->executeSteps($sn, $steps);
        });
    }

    /**
     * Autoriza (provisiona) un ONT en la OLT.
     *
     * $data must contain:
     *   sn              string  — SN del ONT (debe estar en el allow-list)
     *   frame           int     — frame (normalmente 0)
     *   slot            int
     *   port            int
     *   ont_id          int
     *   line_profile_id int     — perfil de línea (line-profile-id en VRP)
     *   srv_profile_id  int     — perfil de servicio (srvprofile-id en VRP)
     *   desc            string  — descripción (opcional)
     *   svlan           int     — outer VLAN del service-port
     *   user_vlan       int     — VLAN del usuario
     *   cvlan           int|null — inner VLAN para QinQ; null = single-tag
     *   gemport         int
     *
     * Genera: addOnt() + addServicePort() (dos secuencias de comandos).
     * ⚠️ Pausar el sync de SmartOLT antes de ejecutar en modo live.
     */
    public function authorizeOnu(array $data): array
    {
        return $this->withSession(function () use ($data) {
            try {
                $sn = strtoupper(trim($data['sn'] ?? ''));

                if ($sn === '') {
                    return ['success' => false, 'message' => "authorizeOnu: 'sn' is required in \$data."];
                }

                $this->guard->assertWriteTargetAllowed($sn);

                $frame  = (int) ($data['frame']           ?? $this->frame);
                $slot   = (int) ($data['slot']            ?? 0);
                $port   = (int) ($data['port']            ?? 0);
                $ontId  = (int) ($data['ont_id']          ?? 0);
                $lpId   = (int) ($data['line_profile_id'] ?? 0);
                $spId   = (int) ($data['srv_profile_id']  ?? 0);
                $desc   = (string) ($data['desc']         ?? '');
                $svlan  = (int) ($data['svlan']           ?? 0);
                $uvlan  = (int) ($data['user_vlan']       ?? 0);
                $cvlan  = isset($data['cvlan']) ? (int) $data['cvlan'] : null;
                $gp     = (int) ($data['gemport']         ?? 1);

                $addSteps = HuaweiCommandBuilder::addOnt($frame, $slot, $port, $ontId, $sn, $lpId, $spId, $desc);
                $spSteps  = HuaweiCommandBuilder::addServicePort($svlan, $frame, $slot, $port, $ontId, $gp, $uvlan, $cvlan);
                $steps    = array_merge($addSteps, $spSteps);

                if ($this->dryRun) {
                    return $this->dryRunResult($sn, $steps);
                }

                // ⚠️ VERIFY in W3 live: after `return` the transport is in user-view,
                // but service-port is a config-view command. Validate that VRP accepts
                // it in user-view or add enterConfigView() before the sp step.
                return $this->executeSteps($sn, $steps);
            } catch (WriteExecutionAbortedException $e) {
                return ['success' => false, 'message' => $e->getMessage(), 'aborted_at' => $e->failedCmd];
            } catch (WriteTargetDeniedException $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
    }

    /**
     * Cambio de perfil de velocidad — NOT IMPLEMENTED (requiere análisis de DBA profiles en VRP).
     */
    public function setOnuSpeedProfile(string $onuId, array $data): array
    {
        throw new WriteNotEnabledException('setOnuSpeedProfile');
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /**
     * Run $fn() inside a Telnet session.
     *
     * Cron context  — transport already open (cron called openSession()): run fn() directly,
     *                 no lock acquisition and no open/close (the cron manages lifecycle).
     * Web context   — transport closed: acquire a 60s lock, open, fn(), close, release.
     *                 If the lock is not available (cron running), return syncing=true
     *                 with a user-visible message; the caller should fall back to cached DB data.
     */
    private function withSession(callable $fn): array
    {
        if ($this->transport->isOpen()) {
            return $fn();
        }

        $lock = Cache::lock("olt-huawei-telnet-{$this->oltId}", 60);
        if (! $lock->get()) {
            return [
                'success' => false,
                'syncing' => true,
                'message' => 'OLT Huawei ocupada (sincronización en progreso). Intente en unos segundos.',
            ];
        }

        try {
            $this->transport->open();
            return $fn();
        } catch (Throwable $e) {
            return $this->error($e);
        } finally {
            try { $this->transport->close(); } catch (Throwable) {}
            $lock->release();
        }
    }

    /**
     * Write operation scaffold: open session → look up ONT → check allow-list → $fn().
     *
     * The ONT lookup (display ont info) serves dual purpose:
     *   1. Confirms the ONT exists at the given onuId before any write is attempted.
     *   2. Resolves the SN for the allow-list gate (rebootOnu / deauthorizeOnu / setOnuEnabled
     *      receive only onuId, not SN — the SN is read from the live OLT).
     *
     * $fn receives (frame, slot, port, ontId, sn) and returns the result array.
     */
    private function withWriteTarget(string $onuId, callable $fn): array
    {
        return $this->withSession(function () use ($onuId, $fn) {
            try {
                [$frame, $slot, $port, $ontId] = $this->parseOnuId($onuId);

                // Resolve SN using interface-context syntax — the only form confirmed on live OLT.
                // "display ont info {f} {s} {p} {id}" (4 space-separated numbers) is NOT a valid
                // VRP command on MA5800-X7 V100R018; it returns an error that OntInfoParser can't
                // parse (W3 dry-run confirmed: "ONT not found").
                // Pattern: same as collectAllOnts() and fetchOpticalForOnt() — proven in B3/W3.
                $this->transport->enterGponInterface($frame, $slot);
                $output = $this->transport->exec("display ont info {$port} {$ontId}");
                $this->transport->leaveToUserView();

                $onts   = OntInfoParser::parse($output, $this->logger);

                if (empty($onts)) {
                    return ['success' => false, 'message' => "ONT not found: {$onuId}"];
                }

                $sn = $this->normalizeSn($onts[0]['sn']);

                $this->guard->assertWriteTargetAllowed($sn);

                return $fn($frame, $slot, $port, $ontId, $sn);
            } catch (WriteExecutionAbortedException $e) {
                $this->logger->error('[olt-huawei] driver:write-aborted', [
                    'cmd'      => $e->failedCmd,
                    'response' => $e->rawResponse,
                ]);
                return [
                    'success'    => false,
                    'message'    => $e->getMessage(),
                    'aborted_at' => $e->failedCmd,
                    'response'   => $e->rawResponse,
                ];
            } catch (WriteTargetDeniedException $e) {
                return ['success' => false, 'message' => $e->getMessage()];
            } catch (Throwable $e) {
                return $this->error($e);
            }
        });
    }

    /**
     * Execute a sequence of write steps against the live OLT.
     *
     * Called by rebootOnu / deauthorizeOnu / setOnuEnabled / authorizeOnu / descOnt
     * only when dry_run=false. The dry-run path goes through dryRunResult() instead.
     *
     * Guard is checked FIRST (before any write byte is sent) as defense in depth;
     * withWriteTarget() callers already checked it, but authorizeOnu() is called
     * without withWriteTarget() so the check here is the authoritative gate.
     *
     * Nav steps (nav=true) are routed to HuaweiTransport navigation methods which
     * maintain the internal view-state ($view) used by currentPrompt().
     * Write steps (nav=false) go through execWrite() which bypasses ReadOnlyGuard.
     *
     * @param  array<array{cmd:string,nav:bool,confirm:string|null}> $steps
     * @throws WriteTargetDeniedException    SN not in WRITE_ALLOW_LIST (before any I/O)
     * @throws WriteExecutionAbortedException VRP returned Error/Failure on a step
     */
    private function executeSteps(string $sn, array $steps): array
    {
        // Guard BEFORE any write I/O (defense in depth)
        $this->guard->assertWriteTargetAllowed($sn);

        $transcript = [];

        foreach ($steps as $step) {
            $cmd     = $step['cmd'];
            $isNav   = (bool) ($step['nav']     ?? false);
            $confirm = $step['confirm'] ?? null;

            if ($isNav) {
                // Route to transport's state-aware navigation methods so $view is kept correct
                if (preg_match('/^interface gpon (\d+)\/(\d+)$/i', trim($cmd), $m)) {
                    $this->transport->enterGponInterface((int) $m[1], (int) $m[2]);
                } elseif (strtolower(trim($cmd)) === 'return') {
                    $this->transport->leaveToUserView();
                }
                $transcript[] = ['cmd' => $cmd, 'response' => ''];
                continue;
            }

            // Write step — bypasses ReadOnlyGuard.assertAllowed() inside execWrite()
            $response = $this->transport->execWrite($cmd, $confirm);

            $this->logger->notice('[olt-huawei] execute-steps:write', [
                'sn'       => $sn,
                'cmd'      => $cmd,
                'response' => $response,
            ]);

            // ABORT on any VRP error indicator:
            // % = VRP unknown-command / parameter-error prefix (e.g. "% Unknown command")
            // Error:/Failure: = explicit error headers  |  failed = mid-line failure word
            if (preg_match('/^\s*(%|Error|Failure|failed)/im', $response)) {
                $this->logger->error('[olt-huawei] execute-steps:abort', [
                    'sn'       => $sn,
                    'cmd'      => $cmd,
                    'response' => $response,
                ]);
                throw new WriteExecutionAbortedException($cmd, $response, $transcript);
            }

            $transcript[] = ['cmd' => $cmd, 'response' => $response];
        }

        $this->logger->notice('[olt-huawei] execute-steps:complete', [
            'sn'    => $sn,
            'steps' => count($transcript),
        ]);

        return ['success' => true, 'sn' => $sn, 'dry_run' => false, 'transcript' => $transcript];
    }

    /**
     * Build the dry-run response: command strings with no I/O performed.
     *
     * @param array<array{cmd:string,nav:bool,confirm:string|null}> $steps
     */
    private function dryRunResult(string $sn, array $steps): array
    {
        $cmds = HuaweiCommandBuilder::cmdStrings($steps);

        $this->logger->notice('[olt-huawei] driver:dry-run', [
            'sn'       => $sn,
            'commands' => $cmds,
        ]);

        return [
            'success'  => true,
            'dry_run'  => true,
            'sn'       => $sn,
            'commands' => $cmds,
        ];
    }

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
                        // 360s: a loaded port (≥100 ONTs) generates many More pages;
                        // measured in B3a against 0/2/8 (112 ONTs) to validate this budget.
                        $output  = $this->transport->exec("display ont info {$port} all", 360);
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
                        // 360s: same budget as ont info (loaded port may paginate heavily).
                        $optOutput = $this->transport->exec("display ont optical-info {$port} all", 360);
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
