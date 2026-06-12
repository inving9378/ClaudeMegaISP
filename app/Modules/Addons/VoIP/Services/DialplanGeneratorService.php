<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Genera megaisp_grupos.conf a partir de los datos en BD (grupos de timbrado
 * y binding troncal→grupo), y aplica los contextos Asterisk correspondientes
 * en ps_endpoints via realtime.
 *
 * Patrón: regenerar-todo idempotente. Llamar en cada save de grupo o troncal.
 */
class DialplanGeneratorService
{
    private string $gruposFile;
    private string $dialplanFile;

    public function __construct()
    {
        $this->gruposFile   = storage_path('app/asterisk/megaisp_grupos.conf');
        $this->dialplanFile = storage_path('app/asterisk/megaisp_dialplan.conf');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUNTO DE ENTRADA PÚBLICO
    // ─────────────────────────────────────────────────────────────────────────

    public function regenerar(): void
    {
        $this->ensureGruposInclude();

        $grupos    = GrupoTimbrado::with(['extensiones' => fn ($q) => $q->orderByPivot('orden')])
                        ->where('activo', true)
                        ->get();

        $troncales = Troncal::whereNotNull('grupo_entrante_id')->with('grupoEntrante')->get();

        $content = $this->buildContent($grupos, $troncales);
        file_put_contents($this->gruposFile, $content, LOCK_EX);

        $this->applyEndpointContexts($troncales);

        Log::info(sprintf(
            'VoIP: megaisp_grupos.conf regenerado — %d grupos, %d troncales con ruteo entrante.',
            $grupos->count(),
            $troncales->count()
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONSTRUCCIÓN DEL ARCHIVO
    // ─────────────────────────────────────────────────────────────────────────

    private function buildContent($grupos, $troncales): string
    {
        $lines = [];
        $lines[] = '; MegaISP — Grupos de Timbrado — generado automáticamente. No editar manualmente.';
        $lines[] = '; Regenerado: ' . now()->toDateTimeString();
        $lines[] = '';

        // Un contexto de entrada por cada troncal con grupo asignado
        foreach ($troncales as $troncal) {
            if (!$troncal->grupoEntrante) {
                continue;
            }
            $lines[] = $this->buildInboundTrunkContext($troncal);
        }

        // Contexto del grupo para cada grupo activo
        foreach ($grupos as $grupo) {
            $lines[] = $this->buildGrupoContext($grupo);
        }

        return implode("\n", $lines);
    }

    private function buildInboundTrunkContext(Troncal $troncal): string
    {
        $ctxName = "inbound-trunk-{$troncal->id}";
        $grupoId = $troncal->grupoEntrante->id;

        return implode("\n", [
            ";── Entrante troncal: {$troncal->nombre} → grupo-{$grupoId} ({$troncal->grupoEntrante->nombre}) ──",
            "[{$ctxName}]",
            "exten = s,1,NoOp(Entrante {$troncal->nombre}: CallerID=\${CALLERID(all)})",
            " same = n,Answer()",
            " same = n,Wait(1)",
            " same = n,Set(CDR(userfield)=inbound)",
            " same = n,Goto(grupo-{$grupoId},s,1)",
            "",
            "exten = _X.,1,NoOp(DID entrante {$troncal->nombre}: \${EXTEN})",
            " same = n,Answer()",
            " same = n,Wait(1)",
            " same = n,Set(CDR(userfield)=inbound)",
            " same = n,Goto(grupo-{$grupoId},s,1)",
            "",
            "exten = i,1,Hangup()",
            "exten = t,1,Hangup()",
            "",
        ]);
    }

    private function buildGrupoContext(GrupoTimbrado $grupo): string
    {
        $lines    = [];
        $ringTime = $grupo->ring_time ?? 20;
        $miembros = $grupo->extensiones;

        $lines[] = ";── Grupo: {$grupo->nombre} (id={$grupo->id}, estrategia={$grupo->estrategia}) ──";
        $lines[] = "[grupo-{$grupo->id}]";

        if ($miembros->isEmpty()) {
            $lines[] = "exten = s,1,NoOp(Grupo {$grupo->id} sin miembros)";
            $lines[] = " same = n," . $this->fallbackLine($grupo);
            $lines[] = "";
            $lines[] = "exten = i,1,Hangup()";
            $lines[] = "exten = t,1,Hangup()";
            $lines[] = "";
            return implode("\n", $lines);
        }

        $lines[] = "exten = s,1,NoOp(Grupo {$grupo->id} — {$grupo->nombre} — {$grupo->estrategia})";

        if ($grupo->estrategia === 'ringall') {
            // Todos timbran a la vez
            $dialStr  = $miembros->map(fn ($e) => "PJSIP/{$e->numero}")->implode('&');
            $lines[] = " same = n,Dial({$dialStr},{$ringTime})";
        } else {
            // hunt / memoryhunt: secuencial por orden de pivot
            foreach ($miembros as $ext) {
                $lines[] = " same = n,Dial(PJSIP/{$ext->numero},{$ringTime})";
            }
        }

        $lines[] = " same = n," . $this->fallbackLine($grupo);
        $lines[] = "";
        $lines[] = "exten = i,1,Hangup()";
        $lines[] = "exten = t,1,Hangup()";
        $lines[] = "";

        return implode("\n", $lines);
    }

    private function fallbackLine(GrupoTimbrado $grupo): string
    {
        return match ($grupo->destino_fallback) {
            'repetir' => "Goto(grupo-{$grupo->id},s,1)",
            'buzon'   => "VoiceMail(default,u)",
            default   => "Hangup()",
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // APLICAR CONTEXTOS EN ps_endpoints
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Actualiza ps_endpoints.context para reflejar el ruteo generado.
     * - Trunk con grupo → context = inbound-trunk-{id}
     * - Trunk sin grupo → context = valor de voip_troncales.contexto (o from-trunk)
     */
    private function applyEndpointContexts($troncalesConGrupo): void
    {
        $db = DB::connection('asterisk_rt');

        // Trunks con grupo entrante
        foreach ($troncalesConGrupo as $troncal) {
            $db->table('ps_endpoints')
               ->where('id', $troncal->endpointId())
               ->update(['context' => "inbound-trunk-{$troncal->id}"]);
        }

        // Trunks sin grupo → restaurar al contexto configurado en BD
        $idsConGrupo = $troncalesConGrupo->pluck('id')->all();
        Troncal::whereNull('grupo_entrante_id')->get()->each(function (Troncal $t) use ($db) {
            $ctx = $t->contexto ?: 'from-trunk';
            $db->table('ps_endpoints')
               ->where('id', $t->endpointId())
               ->update(['context' => $ctx]);
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INCLUDE IDEMPOTENTE
    // ─────────────────────────────────────────────────────────────────────────

    private function ensureGruposInclude(): void
    {
        if (!file_exists($this->dialplanFile)) {
            return;
        }

        $needle = '#include "' . $this->gruposFile . '"';
        $content = file_get_contents($this->dialplanFile);

        if (!str_contains($content, $needle)) {
            file_put_contents(
                $this->dialplanFile,
                rtrim($content) . "\n\n" . $needle . "\n",
                LOCK_EX
            );
        }
    }
}
