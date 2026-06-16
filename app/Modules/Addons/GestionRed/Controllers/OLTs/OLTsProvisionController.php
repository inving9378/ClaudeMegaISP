<?php

namespace App\Modules\Addons\GestionRed\Controllers\OLTs;

use App\Http\Controllers\Controller;
use App\Models\Olt;
use App\Services\OltDriver\Huawei\HuaweiCommandBuilder;
use App\Services\OltDriver\Huawei\ProvisionPreCheckService;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\NullLogger;

/**
 * B4-W2: Provisión de ONTs — vista previa con dry-run.
 *
 * POST /olts/provision/preview
 *   Corre los 6 pre-checks (offline 1-4; perfiles omitidos en W2) y
 *   devuelve la secuencia de comandos VRP que se ejecutaría, junto con
 *   la referencia SmartOLT W0 si el SN es el ONT de laboratorio.
 *
 * GUARDRAIL: sin conexión a la OLT — todos los comandos se generan
 * estáticamente. dry_run=true siempre en W2.
 */
class OLTsProvisionController extends Controller
{
    /** SN del ONT de laboratorio — incluye datos de referencia W0 en el preview. */
    private const LAB_SN = 'HWTCFEFCC9A2';

    /**
     * Reference data captured from SmartOLT W0 fixtures (2026-06-15).
     * Allows side-by-side comparison between proposed and existing config.
     */
    private const W0_REFERENCE = [
        'source'     => 'SmartOLT W0 fixture (2026-06-15)',
        'index'      => 1012,
        'svlan'      => 105,
        'cvlan'      => 200,
        'user_vlan'  => 200,
        'gemport'    => 1,
        'state'      => 'up',
        'upload'     => '50M',
        'download'   => '50M',
    ];

    public function preview(Request $request): JsonResponse
    {
        if (! auth()->user()->can('gestion-red.provision.preview')) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'sn'              => ['required', 'string', 'max:30'],
            'olt_id'          => ['required', 'integer', 'min:1'],
            'slot'            => ['required', 'integer', 'min:0'],
            'port'            => ['required', 'integer', 'min:0'],
            'ont_id'          => ['required', 'integer', 'min:0'],
            'line_profile_id' => ['required', 'integer', 'min:0'],
            'srv_profile_id'  => ['required', 'integer', 'min:0'],
            'svlan'           => ['required', 'integer', 'min:1', 'max:4094'],
            'user_vlan'       => ['required', 'integer', 'min:1', 'max:4094'],
            'cvlan'           => ['nullable', 'integer', 'min:1', 'max:4094'],
            'gemport'         => ['required', 'integer', 'min:1'],
            'desc'            => ['nullable', 'string', 'max:64'],
        ]);

        $sn     = strtoupper(trim($validated['sn']));
        $oltId  = (int) $validated['olt_id'];
        $slot   = (int) $validated['slot'];
        $port   = (int) $validated['port'];
        $ontId  = (int) $validated['ont_id'];
        $lpId   = (int) $validated['line_profile_id'];
        $spId   = (int) $validated['srv_profile_id'];
        $svlan  = (int) $validated['svlan'];
        $uvlan  = (int) $validated['user_vlan'];
        $cvlan  = isset($validated['cvlan']) ? (int) $validated['cvlan'] : null;
        $gp     = (int) $validated['gemport'];
        $desc   = (string) ($validated['desc'] ?? '');
        $frame  = 0; // MA5800-X7 lab always frame 0

        // ── Pre-checks (offline — W2 skips live profile reads) ──────────────
        $svc     = new ProvisionPreCheckService(new ReadOnlyGuard(new NullLogger()));
        $checks  = $svc->runAll(
            ['sn' => $sn, 'olt_id' => $oltId, 'slot' => $slot, 'port' => $port,
             'line_profile_id' => $lpId, 'srv_profile_id' => $spId],
            null,   // line-profiles: null = omitted (no live SSH in W2)
            null,   // srv-profiles:  null = omitted
        );

        // ── Command generation (purely static — zero I/O) ───────────────────
        $addSteps = HuaweiCommandBuilder::addOnt($frame, $slot, $port, $ontId, $sn, $lpId, $spId, $desc);
        $spSteps  = HuaweiCommandBuilder::addServicePort($svlan, $frame, $slot, $port, $ontId, $gp, $uvlan, $cvlan);
        $commands = HuaweiCommandBuilder::cmdStrings(array_merge($addSteps, $spSteps));

        // ── Reference comparison (lab ONT only) ─────────────────────────────
        $reference = strtoupper($sn) === self::LAB_SN ? self::W0_REFERENCE : null;

        return response()->json([
            'dry_run'    => true,
            'sn'         => $sn,
            'checks'     => $checks,
            'all_passed' => ProvisionPreCheckService::allPassed($checks),
            'commands'   => $commands,
            'reference'  => $reference,
        ]);
    }
}
