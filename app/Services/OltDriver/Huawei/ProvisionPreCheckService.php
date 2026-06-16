<?php

namespace App\Services\OltDriver\Huawei;

use App\Services\OltDriver\Huawei\Parsers\ProfileListParser;
use Illuminate\Support\Facades\DB;

/**
 * Runs pre-checks before provisioning an ONT on a Huawei OLT.
 *
 * Check groups:
 *   Offline (DB only, always fast):
 *     1. SN in allow-list
 *     2. SN in autofind (olt_unconfigured_onus)
 *     3. ONT not already provisioned (olt_onus)
 *     4. Slot/port exists in DB (olt_pon_ports)
 *
 *   Live driver read (optional — pass pre-loaded profiles, or null to skip):
 *     5. Line-profile ID exists
 *     6. Srv-profile ID exists
 *
 * Each check returns:
 *   ['ok' => bool|null, 'label' => string, 'message' => string, 'data' => mixed]
 *   ok=null means the check was skipped (profiles not provided).
 */
class ProvisionPreCheckService
{
    public function __construct(
        private readonly ReadOnlyGuard $guard = new ReadOnlyGuard(),
    ) {}

    /**
     * Run all checks and return an indexed result array.
     *
     * @param  array{sn:string, olt_id:int, slot:int, port:int, line_profile_id:int, srv_profile_id:int} $params
     * @param  list<array{id:int,name:string,bindings:int}>|null  $lineProfiles  null = skip check 5
     * @param  list<array{id:int,name:string,bindings:int}>|null  $srvProfiles   null = skip check 6
     * @return list<array{check:int,ok:bool|null,label:string,message:string,data:mixed}>
     */
    public function runAll(array $params, ?array $lineProfiles = null, ?array $srvProfiles = null): array
    {
        $sn           = strtoupper(trim($params['sn'] ?? ''));
        $oltId        = (int) ($params['olt_id']          ?? 0);
        $slot         = (int) ($params['slot']            ?? 0);
        $port         = (int) ($params['port']            ?? 0);
        $lineProfileId = (int) ($params['line_profile_id'] ?? 0);
        $srvProfileId  = (int) ($params['srv_profile_id']  ?? 0);

        return [
            array_merge(['check' => 1], $this->checkSnInAllowList($sn)),
            array_merge(['check' => 2], $this->checkSnInAutofind($sn, $oltId)),
            array_merge(['check' => 3], $this->checkOntNotProvisioned($sn, $oltId)),
            array_merge(['check' => 4], $this->checkSlotPortExists($oltId, $slot, $port)),
            array_merge(['check' => 5], $this->checkLineProfileExists($lineProfileId, $lineProfiles)),
            array_merge(['check' => 6], $this->checkSrvProfileExists($srvProfileId, $srvProfiles)),
        ];
    }

    /** Check 1 — SN must be in the write allow-list. */
    public function checkSnInAllowList(string $sn): array
    {
        $sn = strtoupper(trim($sn));
        try {
            $this->guard->assertWriteTargetAllowed($sn);
            return [
                'ok'      => true,
                'label'   => 'SN en allow-list',
                'message' => "SN '{$sn}' autorizado para escritura.",
                'data'    => ['sn' => $sn],
            ];
        } catch (WriteTargetDeniedException $e) {
            return [
                'ok'      => false,
                'label'   => 'SN en allow-list',
                'message' => $e->getMessage(),
                'data'    => ['sn' => $sn],
            ];
        }
    }

    /** Check 2 — SN must appear in olt_unconfigured_onus (autofind detected by the OLT). */
    public function checkSnInAutofind(string $sn, int $oltId): array
    {
        $sn  = strtoupper(trim($sn));
        $row = DB::table('olt_unconfigured_onus')
            ->where('olt_id', $oltId)
            ->whereRaw('UPPER(sn) = ?', [$sn])
            ->select('id', 'sn', 'board', 'port')
            ->first();

        if ($row) {
            return [
                'ok'      => true,
                'label'   => 'SN en autofind',
                'message' => "SN '{$sn}' detectado en autofind (board {$row->board}, puerto {$row->port}).",
                'data'    => (array) $row,
            ];
        }

        return [
            'ok'      => false,
            'label'   => 'SN en autofind',
            'message' => "SN '{$sn}' no aparece en la tabla de ONTs no configurados (olt_id={$oltId}). ¿Ya fue provisionado o aún no se detecta?",
            'data'    => null,
        ];
    }

    /** Check 3 — SN must NOT already be provisioned in olt_onus. */
    public function checkOntNotProvisioned(string $sn, int $oltId): array
    {
        $sn  = strtoupper(trim($sn));
        $row = DB::table('olt_onus')
            ->where('olt_id', $oltId)
            ->whereRaw('UPPER(sn) = ?', [$sn])
            ->select('id', 'sn', 'unique_external_id')
            ->first();

        if ($row === null) {
            return [
                'ok'      => true,
                'label'   => 'ONT no provisionado',
                'message' => "SN '{$sn}' no está provisionado en este OLT — listo para autorizar.",
                'data'    => null,
            ];
        }

        return [
            'ok'      => false,
            'label'   => 'ONT no provisionado',
            'message' => "SN '{$sn}' ya está provisionado (unique_external_id={$row->unique_external_id}). Desautorizar primero.",
            'data'    => (array) $row,
        ];
    }

    /** Check 4 — Slot/port combination must exist in olt_pon_ports. */
    public function checkSlotPortExists(int $oltId, int $slot, int $port): array
    {
        $row = DB::table('olt_pon_ports')
            ->where('olt_id', $oltId)
            ->where('board', $slot)
            ->where('pon_port', $port)
            ->select('id', 'board', 'pon_port', 'admin_status', 'operational_status')
            ->first();

        if ($row) {
            return [
                'ok'      => true,
                'label'   => 'Slot/puerto existe',
                'message' => "Puerto {$slot}/{$port} existe en OLT (estado: {$row->operational_status}).",
                'data'    => (array) $row,
            ];
        }

        return [
            'ok'      => false,
            'label'   => 'Slot/puerto existe',
            'message' => "Puerto {$slot}/{$port} no encontrado en olt_pon_ports para olt_id={$oltId}.",
            'data'    => null,
        ];
    }

    /**
     * Check 5 — Line-profile ID must exist.
     *
     * @param  list<array{id:int,name:string,bindings:int}>|null $profiles  null = skip
     */
    public function checkLineProfileExists(int $profileId, ?array $profiles): array
    {
        if ($profiles === null) {
            return [
                'ok'      => null,
                'label'   => 'Line-profile existe',
                'message' => "Verificación omitida (perfiles no cargados).",
                'data'    => null,
            ];
        }

        $found = ProfileListParser::findById($profiles, $profileId);

        if ($found) {
            return [
                'ok'      => true,
                'label'   => 'Line-profile existe',
                'message' => "line-profile {$profileId} encontrado: '{$found['name']}' ({$found['bindings']} bindings).",
                'data'    => $found,
            ];
        }

        return [
            'ok'      => false,
            'label'   => 'Line-profile existe',
            'message' => "line-profile-id {$profileId} no existe en la OLT.",
            'data'    => null,
        ];
    }

    /**
     * Check 6 — Service-profile ID must exist.
     *
     * @param  list<array{id:int,name:string,bindings:int}>|null $profiles  null = skip
     */
    public function checkSrvProfileExists(int $profileId, ?array $profiles): array
    {
        if ($profiles === null) {
            return [
                'ok'      => null,
                'label'   => 'Srv-profile existe',
                'message' => "Verificación omitida (perfiles no cargados).",
                'data'    => null,
            ];
        }

        $found = ProfileListParser::findById($profiles, $profileId);

        if ($found) {
            return [
                'ok'      => true,
                'label'   => 'Srv-profile existe',
                'message' => "srvprofile {$profileId} encontrado: '{$found['name']}' ({$found['bindings']} bindings).",
                'data'    => $found,
            ];
        }

        return [
            'ok'      => false,
            'label'   => 'Srv-profile existe',
            'message' => "srvprofile-id {$profileId} no existe en la OLT.",
            'data'    => null,
        ];
    }

    /**
     * Returns true only if all checks with ok=true|false (not null) passed.
     *
     * @param  list<array{ok:bool|null,...}> $results
     */
    public static function allPassed(array $results): bool
    {
        foreach ($results as $r) {
            if ($r['ok'] === false) {
                return false;
            }
        }
        return true;
    }
}
