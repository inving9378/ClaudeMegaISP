<?php

namespace App\Modules\Addons\VoIP\Services;

use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Models\IaBotConfig;
use App\Modules\Addons\VoIP\Services\Voz\AudioSocketServer;
use App\Modules\Addons\VoIP\Models\Troncal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        // Sin esto el archivo nuevo quedaba escrito pero Asterisk seguía con el plan
        // anterior hasta la siguiente recarga por otra razón.
        $recargado = app(AsteriskProvisioningService::class)->reloadDialplan();
        if (! $recargado) {
            Log::warning('VoIP: plan de marcado regenerado pero Asterisk no confirmó la recarga (pbx_config).');
        }

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

        // Troncal propia de Campañas/Blaster (entrantes y salientes de las campañas)
        $lines[] = $this->contextosCampanas($grupos);

        // Llamadas del personal a la calle (el restringido no las tiene)
        $lines[] = $this->contextoSalida($this->troncalSalida());

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
            ...self::lineasGrabar(),
            " same = n,Wait(1)",
            " same = n,Set(CDR(userfield)=inbound)",
            " same = n,Goto(grupo-{$grupoId},s,1)",
            "",
            "exten = _X.,1,NoOp(DID entrante {$troncal->nombre}: \${EXTEN})",
            " same = n,Answer()",
            ...self::lineasGrabar(),
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
        array_push($lines, ...self::lineasGrabar());

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
     * `QUEUE_MEMBER(cola,ready)` se puede leer ANTES de Answer() y ANTES de
     * intentar Queue() — confirmado que no depende del estado del canal.
     * "ready" y no "logged" a propósito: "logged" solo cuenta miembros
     * CONFIGURADOS en la cola, sin importar si su teléfono está de verdad
     * conectado — con eso la regla de oro habría dado luz verde aunque nadie
     * pudiera contestar en realidad. "ready" excluye a quien esté
     * desconectado/ocupado — es la que refleja disponibilidad real.
     * Además `joinempty=no` en la propia cola (AsteriskProvisioningService::
     * provisionarCola) es un segundo candado, a nivel de Asterisk, por si
     * este chequeo de dialplan se saltara por algún camino no previsto.
     *
     * Grabación (`MixMonitor`) arranca justo después de `Answer()`, ANTES del
     * `Playback(beep)` — cubre la llamada completa, incluido el contestador
     * (relleno hoy, la IA real en Fase 6), no solo el tramo con el agente.
     * El nombre de archivo es `${UNIQUEID}.wav` (único por diseño de
     * Asterisk, sin colisiones) y se guarda también como `CDR(grabacion)`
     * para que quede ligado a la fila de `voip_llamadas` sin necesidad de
     * derivarlo después. Formato WAV porque el servidor no trae compilado
     * `format_mp3`: la conversión a MP3 es un paso aparte sobre el archivo ya
     * cerrado (`megavoz:comprimir-grabaciones`, cada 5 min), no parte de este
     * dialplan.
     *
     * MegaVoz Fase 6 (conectar el motor real) — `AudioSocket()` se emite
     * SOLO si `ia_bot_config.enabled` está en true AL GENERAR este archivo
     * (`IaBotController::saveConfig()` ya llama `regenerar()` cada vez que
     * cambia). El % de piloto y el horario de oficina NO viven aquí — los
     * evalúa el daemon EN VIVO por cada llamada
     * (`IaBotConfig::debeAtenderAhora()`), porque son una tirada aleatoria y
     * una ventana de horario, no algo que tenga sentido congelar en texto
     * estático. Si el daemon SÍ está corriendo y decide no atender (fuera
     * del piloto, fuera de horario) cierra el socket casi de inmediato sin
     * decir nada — confirmado en el código fuente de Asterisk
     * (`app_audiosocket.c`: el cierre remoto se detecta como hangup y
     * `AudioSocket()` regresa 0, "normal exit") — y el dialplan sigue
     * exactamente igual que si Fase 6 no existiera.
     *
     * ⚠️ PERO si el daemon simplemente NO ESTÁ CORRIENDO (crash, aún no
     * arrancado, puerto equivocado), `ast_audiosocket_connect()` falla y
     * `audiosocket_exec()` regresa `-1` ANTES de intentar nada más — y un
     * valor negativo de una app de dialplan es la convención clásica de
     * Asterisk para "colgar el canal", NO "seguir a la siguiente
     * prioridad". Confirmado en vivo (2026-09-24): un `channel originate`
     * contra `grupo-1` con el daemon apagado colgó el canal sin que
     * apareciera NUNCA un `ENTERQUEUE` en `queue_log` — la regla de oro
     * (`Answer()` primero, cola después SIEMPRE) se rompía en silencio
     * justo en el caso más probable de estar mal (daemon caído). Por eso
     * el `TrySystem(nc)` de abajo: es un candado de PRE-VUELO barato (nc ya
     * viene en el sistema) que decide, con la MISMA convención segura que
     * ya usa toda la cola (`SYSTEMSTATUS`, que `TrySystem` nunca deja
     * colgar el canal pase lo que pase), si vale la pena intentar
     * `AudioSocket()` siquiera. Sin este candado, dejar `enabled=true` sin
     * el daemon corriendo como servicio persistente (systemd, aún
     * pendiente) desconectaría clientes reales en silencio.
     * `Playback(beep)` sigue sonando SIEMPRE primero, la atienda María o
     * no — es el mismo "algo está pasando" para el que llama en los dos
     * casos, no dos contestadores distintos.
     */
    private function buildColaExten(GrupoTimbrado $grupo, int $ringTime): array
    {
        $nombreCola = $grupo->nombreCola();
        $botHabilitado = IaBotConfig::current()->enabled;
        $botHost = config('voip.bot_voz.host', '127.0.0.1');
        $botPort = config('voip.bot_voz.port', 9099);
        $botHostPuerto = "{$botHost}:{$botPort}";

        return [
            "exten = s,1,NoOp(Grupo {$grupo->id} — {$grupo->nombre} — cola real, MegaVoz Fase 3)",
            " same = n,Answer()",
            ...self::lineasGrabar(),
            " same = n,Playback(beep)  ; contestador — María (si le toca esta llamada) sigue justo después",
            ...($botHabilitado ? [
                " same = n,TrySystem(nc -z -w1 {$botHost} {$botPort})  ; Fase 6 — pre-vuelo: sin esto, un AudioSocket() a un daemon caído cuelga el canal en vez de seguir a la cola (ver nota arriba)",
                " same = n,GotoIf(\$[\"\${SYSTEMSTATUS}\" != \"SUCCESS\"]?sigue_bot)",
                // AudioSocket solo manda el UUID: ahí va el número del que llama (27-nov) para que
                // la asistente sepa si es cliente. xxxxxxxx-xxxx-ca11-NNNN-NNNNNNNNNNNN, el número
                // rellenado con «f» a la izquierda (AudioSocketServer::numeroQueLlama). Solo dígitos
                // y «f»: siempre es un UUID válido (uno inválido haría que AudioSocket colgara).
                " same = n,Set(BOT_NUM=ffffffffffffffff\${FILTER(0-9,\${CALLERID(num)})})",
                " same = n,Set(BOT_NUM=\${BOT_NUM:-16})",
                " same = n,Set(BOT_UUID=\${UUID():0:14}" . AudioSocketServer::MARCA_NUMERO . "-\${BOT_NUM:0:4}-\${BOT_NUM:4:12})",
                " same = n,AudioSocket(\${BOT_UUID},{$botHostPuerto})  ; el daemon decide en vivo si atiende (piloto/horario)",
                " same = n(sigue_bot),NoOp(Fase 6 — fin del intento de bot)",
            ] : []),
            " same = n,GotoIf(\$[\${QUEUE_MEMBER({$nombreCola},ready)} > 0]?con_agente:sin_agente)",
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

    /**
     * Campañas/Blaster tiene SU troncal (Config. VoIP del módulo, endpoint
     * voip.trunk_endpoint_id) y las campañas ya no eligen otra (Irving, 23-nov). Lo que
     * entra por ella timbra en el grupo elegido ahí, o en el primer grupo activo.
     */
    private function contextosCampanas($grupos): string
    {
        if (! Schema::hasTable('voip_configuracion')) {
            return '';
        }

        $elegido = Schema::hasColumn('voip_configuracion', 'grupo_entrante_id')
            ? DB::table('voip_configuracion')->orderBy('id')->value('grupo_entrante_id')
            : null;
        $grupo = $grupos->firstWhere('id', $elegido) ?? $grupos->sortBy('id')->first();

        return $this->planCampanas(
            (string) config('voip.trunk_context', 'from-servnet'),
            (string) config('voip.ami_context', 'cobranza-blaster'),
            $grupo?->id,
            $grupo?->nombre
        );
    }

    /**
     * Entrada: [contexto de la troncal] → grupo. Salida: el blaster origina por
     * PJSIP/<troncal>/<número> y, al contestar, la llamada cae en [contexto del blaster],
     * que reproduce el audio de la campaña (antes stubs/extensions-cobranza-blaster.conf,
     * que había que copiar a mano y nunca llegó a ninguna central).
     */
    public function planCampanas(string $ctxEntrada, string $ctxBlaster, ?int $grupoId, ?string $grupoNombre): string
    {
        $endpoint = (string) config('voip.trunk_endpoint_id', 'servnet');

        $entrada = $grupoId
            ? [
                ";── Entrante troncal de Campañas/Blaster ({$endpoint}) → grupo-{$grupoId} ({$grupoNombre}) ──",
                "[{$ctxEntrada}]",
                'exten = s,1,NoOp(Entrante Campañas/Blaster: CallerID=${CALLERID(all)})',
                ' same = n,Answer()',
                ...self::lineasGrabar(),
                ' same = n,Wait(1)',
                ' same = n,Set(CDR(userfield)=inbound)',
                " same = n,Goto(grupo-{$grupoId},s,1)",
                '',
                'exten = _X.,1,NoOp(DID entrante Campañas/Blaster: ${EXTEN})',
                ' same = n,Answer()',
                ...self::lineasGrabar(),
                ' same = n,Wait(1)',
                ' same = n,Set(CDR(userfield)=inbound)',
                " same = n,Goto(grupo-{$grupoId},s,1)",
            ]
            : [
                ";── Entrante troncal de Campañas/Blaster ({$endpoint}): no hay grupo de timbrado activo ──",
                "[{$ctxEntrada}]",
                'exten = s,1,NoOp(Entrante Campañas/Blaster sin grupo de timbrado activo)',
                ' same = n,Hangup()',
                'exten = _X.,1,Goto(s,1)',
            ];

        return implode("\n", array_merge($entrada, [
            '',
            'exten = i,1,Hangup()',
            'exten = t,1,Hangup()',
            '',
            ";── Salientes de Campañas/Blaster: Originate por PJSIP/{$endpoint}/<número> ──",
            "[{$ctxBlaster}]",
            'exten = s,1,NoOp(Campaña: llamada ${COBRANZA_LLAMADA_ID} -> ${COBRANZA_NOMBRE})',
            ' same = n,Answer()',
            ...self::lineasGrabar(),
            ' same = n,Wait(1)',
            ' same = n,Set(AUDIO_BASE=${CUT(COBRANZA_AUDIO,.,1)})',
            ' same = n,Background(${AUDIO_BASE})',
            ' same = n,WaitExten(8)',
            ' same = n,Hangup()',
            '',
            '; «1» = pide asesor: aún sin destino (roadmap #190), mensaje y colgar.',
            'exten = 1,1,NoOp(Campaña: llamada ${COBRANZA_LLAMADA_ID} pide asesor)',
            ' same = n,Playback(vm-nobodyavail)',
            ' same = n,Hangup()',
            '',
            'exten = i,1,Hangup()',
            'exten = t,1,Hangup()',
            '',
        ]));
    }

    /**
     * La troncal por la que sale el personal: la primera activa que puede sacar llamadas
     * (dirección saliente o ambas) y no tiene un propósito propio (UCM, cobranza, avisos…).
     */
    public function troncalSalida(): ?Troncal
    {
        return Troncal::where('activo', true)
            ->whereIn('direccion', ['saliente', 'ambas'])
            ->whereNull('proposito')
            ->orderBy('id')
            ->first();
    }

    /**
     * Llamadas del personal a la calle.
     *
     * Reabre [from-internal]: Asterisk junta las extensiones de un contexto aunque esté en
     * dos archivos, así que la regla no depende de extensions.conf (lo escribe root y en
     * algunas centrales está editado a mano). Solo números nacionales de 10 dígitos: nada
     * internacional, que es por donde entra el fraude telefónico si alguien adivina la
     * contraseña de una extensión. El contexto restringido no la incluye: un usuario
     * bloqueado sigue sin poder salir.
     *
     * Marca a la URI del proveedor (host:puerto de la troncal) en vez de al AOR, que en las
     * troncales de registro no lleva contacto fijo; la autenticación y el from_user son los
     * del endpoint de la troncal.
     */
    public function contextoSalida(?Troncal $troncal): string
    {
        if (! $troncal || ! $troncal->host) {
            return ";── Llamadas a la calle: no hay troncal activa de uso general (saliente/ambas, sin propósito) ──\n";
        }

        $endpoint = $troncal->endpointId();
        $destino  = 'sip:${EXTEN}@' . $troncal->host . ($troncal->puerto ? ':' . $troncal->puerto : '');

        return implode("\n", [
            ";── Llamadas a la calle por la troncal «{$troncal->nombre}» ({$endpoint}) ──",
            '[from-internal]',
            'exten = _[2-9]XXXXXXXXX,1,NoOp(Saliente: ${CALLERID(num)} -> ${EXTEN})',
            ' same = n,Set(CDR(userfield)=saliente)',
            ...self::lineasGrabar(),
            " same = n,Dial(PJSIP/{$endpoint}/{$destino},60)",
            self::lineaSinContestarSinGrabacion(),
            ' same = n,Hangup()',
            '; Mismo número con lada de país o prefijo viejo (como viene en los contactos del',
            '; celular o de Zoiper): se reduce a los 10 dígitos que pide el proveedor.',
            'exten = _52[2-9]XXXXXXXXX,1,Goto(${EXTEN:2},1)',
            'exten = _+52[2-9]XXXXXXXXX,1,Goto(${EXTEN:3},1)',
            'exten = _521[2-9]XXXXXXXXX,1,Goto(${EXTEN:3},1)',
            'exten = _+521[2-9]XXXXXXXXX,1,Goto(${EXTEN:4},1)',
            'exten = _04[45][2-9]XXXXXXXXX,1,Goto(${EXTEN:3},1)',
            'exten = _01[2-9]XXXXXXXXX,1,Goto(${EXTEN:2},1)',
            '',
        ]);
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
            ...self::lineasGrabar(),
            ' same = n,Dial(PJSIP/${EXTEN},20)',
            self::lineaSinContestarSinGrabacion(),
            ' same = n,Hangup()',
            '',
            '; Internos 4 dígitos (1000-9999)',
            'exten = _[1-9]XXX,1,NoOp(Restringido-interno: ${CALLERID(num)} → ${EXTEN})',
            ' same = n,Set(CDR(userfield)=internal_restricted)',
            ...self::lineasGrabar(),
            ' same = n,Dial(PJSIP/${EXTEN},20)',
            self::lineaSinContestarSinGrabacion(),
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

    /**
     * Grabación de la llamada (24-nov, pedido de Irving: todas las llamadas se graban y se
     * escuchan). Antes solo la cola grababa, así que en una central con grupos normales
     * (Dial) no quedaba ninguna grabación, y tampoco de salientes, internas ni campañas.
     *
     * Se repite en cada punto por donde entra una llamada (troncal, grupo, cola, salida,
     * campaña). GRABANDO evita una segunda grabación cuando la misma llamada pasa por
     * dos de ellos (troncal → grupo). Va en línea, sin Gosub a un contexto compartido:
     * extensions.conf (plantilla de root) y este archivo se regeneran por separado y una
     * subrutina que todavía no existe cuelga la llamada. El archivo se llama como siempre,
     * <UNIQUEID>.wav, y queda en CDR(grabacion) para ligarlo a su fila de voip_llamadas.
     *
     * @return array<int, string>
     */
    public static function lineasGrabar(): array
    {
        return [
            ' same = n,ExecIf($["${GRABANDO}" = ""]?Set(CDR(grabacion)=${UNIQUEID}.wav))',
            ' same = n,ExecIf($["${GRABANDO}" = ""]?MixMonitor(${GRABACIONES_DIR}/${UNIQUEID}.wav))  ; retención y purga: megavoz:purgar-grabaciones',
            ' same = n,Set(GRABANDO=1)',
        ];
    }

    /** Llamada que sale y nadie contesta: sin grabación en el historial (solo grabó el timbre). */
    public static function lineaSinContestarSinGrabacion(): string
    {
        return ' same = n,ExecIf($["${DIALSTATUS}" != "ANSWER"]?Set(CDR(grabacion)=))';
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

        // Con grupo → inbound-trunk-N; sin grupo → el configurado (Troncal::contextoEntrante).
        Troncal::all()->each(function (Troncal $t) use ($db) {
            $db->table('ps_endpoints')
               ->where('id', $t->endpointId())
               ->update(['context' => $t->contextoEntrante()]);
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
