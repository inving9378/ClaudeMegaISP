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
        // #9990718 §6 — fuera del árbol de la aplicación web. Antes esto era
        // storage_path('app/asterisk/…') y /etc/asterisk lo incluía por ruta
        // absoluta: la configuración que Asterisk lee no debe depender de dónde
        // esté instalado MegaISP, y un proceso root no debe leer configuración
        // de un directorio escribible por www-data.
        $dir = rtrim(config('requisitos-voip.asterisk.generados_dir', '/etc/asterisk/megaisp.d'), '/');

        $this->gruposFile   = $dir . '/megaisp_grupos.conf';
        $this->dialplanFile = $dir . '/megaisp_dialplan.conf';
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

        // Contexto restringido (bloqueado): internos sí, troncal no
        $lines[] = $this->buildContextoRestringido();

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

        if ($grupo->es_cola) {
            return implode("\n", array_merge($lines, $this->buildColaExten($grupo, $ringTime)));
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

    /**
     * MegaVoz Fase 3 — "regla de oro" (revisada 23-sep-2026 por David): el
     * asistente SIEMPRE contesta primero, haya o no agente libre — nunca se
     * timbra directo a los agentes. Solo se entra a la cola real después de
     * contestar. `Playback(beep)` es el contestador de RELLENO — es donde se
     * conecta la IA de voz en tiempo real cuando exista (Fase 6); se eligió
     * un sonido core de Asterisk (garantizado presente en cualquier
     * instalación) en vez de un prompt en español para no depender de un
     * archivo que podría no existir en este servidor.
     *
     * `QUEUE_MEMBER(cola,logged)` se puede leer ANTES de Answer() y ANTES de
     * intentar Queue() — confirmado que no depende del estado del canal.
     * Además `joinempty=no` en la propia cola (AsteriskProvisioningService::
     * provisionarCola) es un segundo candado, a nivel de Asterisk, por si
     * este chequeo de dialplan se saltara por algún camino no previsto.
     */
    private function buildColaExten(GrupoTimbrado $grupo, int $ringTime): array
    {
        $nombreCola = $grupo->nombreCola();

        return [
            "exten = s,1,NoOp(Grupo {$grupo->id} — {$grupo->nombre} — cola real, MegaVoz Fase 3)",
            " same = n,Answer()",
            " same = n,Playback(beep)  ; contestador de RELLENO — aquí conecta la IA real (Fase 6)",
            " same = n,GotoIf(\$[\${QUEUE_MEMBER({$nombreCola},logged)} > 0]?con_agente:sin_agente)",
            " same = n(con_agente),Queue({$nombreCola},t,,,{$ringTime})",
            " same = n,Goto(s,fallback)",
            " same = n(sin_agente),NoOp(Sin agentes libres en {$nombreCola} — no se intenta encolar)",
            " same = n(fallback)," . $this->fallbackLine($grupo),
            "",
            "exten = i,1,Hangup()",
            "exten = t,1,Hangup()",
            "",
        ];
    }

    private function buildContextoRestringido(): string
    {
        return implode("\n", [
            ';── Contexto restringido: usuario bloqueado (sin salientes a la calle) ──────',
            '[from-internal-restringido]',
            '',
            '; Internos 3 dígitos (100-999)',
            'exten = _[1-9]XX,1,NoOp(Restringido-interno: ${CALLERID(num)} → ${EXTEN})',
            ' same = n,Set(CDR(userfield)=internal_restricted)',
            ' same = n,Dial(PJSIP/${EXTEN},20)',
            ' same = n,Hangup()',
            '',
            '; Internos 4 dígitos (1000-9999)',
            'exten = _[1-9]XXX,1,NoOp(Restringido-interno: ${CALLERID(num)} → ${EXTEN})',
            ' same = n,Set(CDR(userfield)=internal_restricted)',
            ' same = n,Dial(PJSIP/${EXTEN},20)',
            ' same = n,Hangup()',
            '',
            '; Intento de salida a la calle — DENEGADO',
            'exten = _0[0-9].,1,NoOp(BLOQUEADO saliente: ${CALLERID(num)} → ${EXTEN})',
            ' same = n,Playback(pbx-invalid)',
            ' same = n,Hangup()',
            '',
            'exten = i,1,Hangup()',
            'exten = t,1,Hangup()',
            '',
        ]);
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
