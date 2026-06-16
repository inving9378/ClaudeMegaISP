<?php

namespace App\Services\OltDriver\Huawei;

/**
 * Generates VRP CLI command sequences for Huawei OLT write operations.
 *
 * Returns arrays of step descriptors. Each step has:
 *   'cmd'     => string       — VRP command to send
 *   'nav'     => bool         — true = view-navigation (enter/leave, not a config change)
 *   'confirm' => string|null  — reply to send when the OLT prompts "(y/n)[n]:" after the command
 *
 * No I/O is performed here. This class is purely a command factory.
 * Transport execution (including confirmation handling) is the driver's responsibility.
 *
 * VRP reference: MA5800 V100R018C00 command manual.
 *
 * ⚠️ VERIFY before W3 (first real execution):
 *   • Confirmation prompts — ont reset/ont delete may or may not prompt depending on firmware patch.
 *   • service-port tag-transform — double-tag syntax verified against MA5800-X7 SPH505;
 *     other firmware variants may differ.
 */
class HuaweiCommandBuilder
{
    /**
     * Reset (power-cycle) an ONT.
     *
     * VRP: `ont reset <port-id> <ont-id>` (inside interface gpon {frame}/{slot})
     * Confirmation: "Are you sure to reset ONT? (y/n)[n]:" → 'y'
     */
    public static function rebootOnt(int $frame, int $slot, int $port, int $ontId): array
    {
        return [
            ['cmd' => "interface gpon {$frame}/{$slot}", 'nav' => true,  'confirm' => null],
            ['cmd' => "ont reset {$port} {$ontId}",     'nav' => false,  'confirm' => 'y'],
            ['cmd' => 'return',                          'nav' => true,   'confirm' => null],
        ];
    }

    /**
     * Delete (deauthorize) an ONT from the OLT.
     *
     * VRP: `ont delete <port-id> <ont-id>` (inside interface gpon {frame}/{slot})
     * Confirmation: "Are you sure to delete ONT? (y/n)[n]:" → 'y'
     *
     * ⚠️ This removes ONT config from the OLT. The ONT will appear as "not authorized"
     * until re-provisioned with addOnt() + addServicePort().
     */
    public static function deleteOnt(int $frame, int $slot, int $port, int $ontId): array
    {
        return [
            ['cmd' => "interface gpon {$frame}/{$slot}", 'nav' => true,  'confirm' => null],
            ['cmd' => "ont delete {$port} {$ontId}",    'nav' => false,  'confirm' => 'y'],
            ['cmd' => 'return',                          'nav' => true,   'confirm' => null],
        ];
    }

    /**
     * Authorize (add) an ONT to the OLT.
     *
     * VRP: `ont add <port-id> <ont-id> sn-auth <sn> omci
     *            ont-lineprofile-id <lp> ont-srvprofile-id <sp> [desc "<desc>"]`
     *
     * ⚠️ This only registers the ONT. Call addServicePort() after to activate the data plane.
     */
    public static function addOnt(
        int    $frame,
        int    $slot,
        int    $port,
        int    $ontId,
        string $sn,
        int    $lineProfileId,
        int    $srvProfileId,
        string $desc = '',
    ): array {
        $descPart = $desc !== '' ? " desc \"{$desc}\"" : '';
        $ontCmd   = "ont add {$port} {$ontId} sn-auth {$sn} omci"
                  . " ont-lineprofile-id {$lineProfileId}"
                  . " ont-srvprofile-id {$srvProfileId}"
                  . $descPart;

        return [
            ['cmd' => "interface gpon {$frame}/{$slot}", 'nav' => true,  'confirm' => null],
            ['cmd' => $ontCmd,                           'nav' => false,  'confirm' => null],
            ['cmd' => 'return',                          'nav' => true,   'confirm' => null],
        ];
    }

    /**
     * Add a service-port for an ONT. Must be called from config-view (not inside an interface).
     *
     * Two modes:
     *   single-tag ($cvlan = null):
     *     service-port vlan {svlan} gpon {f}/{s}/{p} ont {id}
     *       gemport {gp} multi-service user-vlan {uvlan} tag-transform default
     *
     *   double-tag / QinQ ($cvlan = int):
     *     service-port vlan {svlan} gpon {f}/{s}/{p} ont {id}
     *       gemport {gp} multi-service user-vlan {uvlan}
     *       tag-transform translate-and-add inner-vlan {cvlan}
     *
     * ⚠️ Verify tag-transform syntax against live OLT output before W3.
     */
    public static function addServicePort(
        int  $svlan,
        int  $frame,
        int  $slot,
        int  $port,
        int  $ontId,
        int  $gemport,
        int  $userVlan,
        ?int $cvlan = null,
    ): array {
        $fsp       = "{$frame}/{$slot}/{$port}";
        $transform = $cvlan !== null
            ? "tag-transform translate-and-add inner-vlan {$cvlan}"
            : 'tag-transform default';

        $cmd = "service-port vlan {$svlan} gpon {$fsp} ont {$ontId}"
             . " gemport {$gemport} multi-service user-vlan {$userVlan} {$transform}";

        return [
            ['cmd' => $cmd, 'nav' => false, 'confirm' => null],
        ];
    }

    /**
     * Activate (re-enable) a deactivated ONT without a full delete+add cycle.
     *
     * VRP: `ont activate <port-id> <ont-id>` (inside interface gpon {frame}/{slot})
     */
    public static function activateOnt(int $frame, int $slot, int $port, int $ontId): array
    {
        return [
            ['cmd' => "interface gpon {$frame}/{$slot}", 'nav' => true,  'confirm' => null],
            ['cmd' => "ont activate {$port} {$ontId}",  'nav' => false,  'confirm' => null],
            ['cmd' => 'return',                          'nav' => true,   'confirm' => null],
        ];
    }

    /**
     * Deactivate (suspend) an ONT without removing its config from the OLT.
     *
     * VRP: `ont deactivate <port-id> <ont-id>` (inside interface gpon {frame}/{slot})
     */
    public static function deactivateOnt(int $frame, int $slot, int $port, int $ontId): array
    {
        return [
            ['cmd' => "interface gpon {$frame}/{$slot}",  'nav' => true,  'confirm' => null],
            ['cmd' => "ont deactivate {$port} {$ontId}", 'nav' => false,  'confirm' => null],
            ['cmd' => 'return',                           'nav' => true,   'confirm' => null],
        ];
    }

    /**
     * Set (or clear) the description label on an ONT.
     *
     * VRP: `ont desc <port-id> <ont-id> <description>` (inside interface gpon {frame}/{slot})
     * Passing an empty $desc generates `undo ont desc` which removes the label entirely.
     * No confirmation prompt, no `save` required (volatile until next save).
     *
     * Inverse: ontDesc($frame, $slot, $port, $ontId, '') — restores the original label (or
     * removes it if the ONT had none) without any service interruption.
     *
     * ⚠️ VERIFY in W3 live: VRP may or may not accept unquoted descriptions with special chars.
     */
    public static function ontDesc(int $frame, int $slot, int $port, int $ontId, string $desc): array
    {
        $safe     = str_replace(['"', "'", "\r", "\n"], '', $desc);
        $writeCmd = $safe !== ''
            ? "ont desc {$port} {$ontId} {$safe}"
            : "undo ont desc {$port} {$ontId}";

        return [
            ['cmd' => "interface gpon {$frame}/{$slot}", 'nav' => true,  'confirm' => null],
            ['cmd' => $writeCmd,                          'nav' => false,  'confirm' => null],
            ['cmd' => 'return',                           'nav' => true,  'confirm' => null],
        ];
    }

    /**
     * Extract the 'cmd' strings from a step sequence (for display / logging).
     *
     * @param  array<array{cmd:string,nav:bool,confirm:string|null}> $steps
     * @return string[]
     */
    public static function cmdStrings(array $steps): array
    {
        return array_map(static fn($s) => $s['cmd'], $steps);
    }
}
