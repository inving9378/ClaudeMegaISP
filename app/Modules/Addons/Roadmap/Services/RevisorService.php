<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Agente REVISOR (#338): segunda opinión INDEPENDIENTE y ADVERSARIAL sobre las decisiones B
 * del decisor del circuito. Contexto FRESCO — una llamada nueva a Claude Sonnet, sin el
 * contexto del ejecutor: su trabajo no es aprobar sino intentar REFUTAR que el cambio sea
 * seguro para que el circuito lo ejecute solo, sin Irving.
 *
 * Sesgo por defecto = ESCALAR. Solo autoriza (aprobado_revisor) B claramente técnico, aditivo,
 * acotado y verificable; cualquier duda o frontera dura (dinero / seguridad-permisos / prod /
 * negocio) → requiere_irving. Todo veredicto queda AUDITADO en `circuito_revisiones` y es
 * REVERSIBLE por Irving.
 *
 * Arranque CONSERVADOR: pre-filtro de alcance (config circuito.revisor.alcance) descarta lo
 * sensible ANTES de gastar IA, y el flag `circuito_revisor` (OFF por default) gatea que el
 * ejecutor ejecute los aprobado_revisor.
 */
class RevisorService
{
    public function __construct(private RoadmapCircuitoService $circuito)
    {
    }

    /**
     * Pre-filtro barato de ALCANCE conservador: si el item menciona términos de la frontera
     * dura (dinero/seguridad/permisos/prod/destructivo), queda FUERA de alcance y ni llega a
     * Sonnet (se escala directo). Denylist configurable en config('circuito.revisor.alcance').
     */
    public function enAlcance(RoadmapItem $item): array
    {
        $deny = (array) config('circuito.revisor.alcance.denylist', []);
        $heno = mb_strtolower(trim(($item->title ?? '') . "\n" . ($item->modulo ?? '') . "\n" . ($item->prompt ?? '')));

        foreach ($deny as $kw) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw !== '' && Str::contains($heno, $kw)) {
                return [
                    'en_alcance' => false,
                    'motivo'     => "Fuera del alcance conservador: menciona \"{$kw}\" (frontera dura dinero/seguridad/prod/negocio).",
                    'kw'         => $kw,
                ];
            }
        }

        return ['en_alcance' => true, 'motivo' => 'Dentro del alcance conservador (sin términos de frontera dura).'];
    }

    /**
     * Revisa un item B con el revisor adversarial. NO cambia el estado del item (eso lo hace
     * aplicarVeredicto). Audita en `circuito_revisiones` y devuelve el veredicto (con _audit_id).
     * FALLA-SEGURA: cualquier error de IA o JSON ilegible ⇒ ESCALA (nunca autoriza a ciegas).
     */
    public function revisar(RoadmapItem $item, ?string $decisorCtx = null): array
    {
        // 1) Pre-filtro de alcance (sin gastar IA en lo sensible).
        $al = $this->enAlcance($item);
        if (! $al['en_alcance']) {
            $v = [
                'veredicto'          => 'escala',
                'en_alcance'         => false,
                'categoria_escalada' => 'fuera_alcance',
                'confianza'          => 'alta',
                'razon'              => $al['motivo'],
                'riesgos'            => [],
            ];
            $v['_audit_id'] = $this->auditar($item, $v, null, $decisorCtx);

            return $v;
        }

        // 2) Revisor adversarial ESCALONADO (#338). Rutina = Sonnet; si queda BORDERLINE (baja
        //    confianza o categoría amplio/duda) y NO es frontera dura clara → 2ª opinión con Opus
        //    (solo en lo difícil, para no quemar límites). Frontera dura ya escala sin gastar Opus.
        $routine = (string) config('circuito.revisor.model_routine', config('circuito.revisor.model', 'claude-sonnet-4-6'));
        $hard    = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');

        $r = $this->callModel($routine, $item, $decisorCtx);
        $v = $r['v'];
        $modeloUsado = $routine;
        $usage = $r['usage'];

        $okRoutine    = ($v['_ok'] ?? true) !== false;
        $fronteraDura = in_array($v['categoria_escalada'] ?? '', ['dinero', 'seguridad', 'prod', 'negocio'], true);
        $borderline   = ($v['confianza'] ?? 'baja') === 'baja'
            || in_array($v['categoria_escalada'] ?? '', ['amplio', 'duda'], true);

        if ($okRoutine && $borderline && ! $fronteraDura && $routine !== $hard) {
            $r2 = $this->callModel($hard, $item, $decisorCtx);
            if (($r2['v']['_ok'] ?? true) !== false) {
                $v = $r2['v'];
                $modeloUsado = $hard . ' (2ª opinión)';
                $usage = $r2['usage'];
            }
        }

        unset($v['_ok']);
        $v['en_alcance'] = true;
        $v['_audit_id']  = $this->auditar($item, $v, [
            'modelo'     => $modeloUsado,
            'tokens_in'  => $usage['input_tokens'] ?? null,
            'tokens_out' => $usage['output_tokens'] ?? null,
        ], $decisorCtx);

        return $v;
    }

    /** Una llamada al modelo dado. Devuelve ['v'=>veredicto parseado, 'usage'=>usage]. Falla-segura. */
    private function callModel(string $model, RoadmapItem $item, ?string $decisorCtx): array
    {
        try {
            $params = [
                'model'      => $model,
                'max_tokens' => (int) config('circuito.revisor.max_tokens', 700),
                'system'     => $this->systemPrompt(),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, $decisorCtx)]],
            ];
            // `temperature` está DEPRECADO en Opus 4.x → solo lo mandamos a modelos que lo aceptan (Sonnet).
            if (stripos($model, 'opus') === false) {
                $params['temperature'] = 0;
            }
            $resp = (new ClaudeApiClient())->messages($params);
            $text = '';
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $text .= $blk['text'] ?? '';
                }
            }

            return ['v' => $this->parse($text), 'usage' => $resp['usage'] ?? []];
        } catch (\Throwable $e) {
            // Falla-segura: IA caída o respuesta ilegible → ESCALA con confianza baja.
            return ['v' => [
                'veredicto'          => 'escala',
                'categoria_escalada' => 'duda',
                'confianza'          => 'baja',
                'razon'              => 'El revisor no pudo emitir veredicto (' . mb_strimwidth($e->getMessage(), 0, 160, '…') . '). Falla-segura → escala.',
                'riesgos'            => ['revisor no concluyente'],
                '_ok'                => false,
            ], 'usage' => []];
        }
    }

    /**
     * Aplica el veredicto al item: autoriza → 'aprobado_revisor'; escala → 'requiere_irving'.
     * Escribe el razonamiento del revisor en comentarios_claude (auditable) y sella la fila de
     * auditoría como aplicada. Respeta el KILL SWITCH (#342): en pausa NUNCA autoriza (escala).
     */
    public function aplicarVeredicto(RoadmapItem $item, array $v, string $actor = 'revisor'): RoadmapItem
    {
        $enPausa   = $this->circuito->isPaused();
        $autoriza  = (($v['veredicto'] ?? 'escala') === 'autoriza') && ! $enPausa;
        $nuevo     = $autoriza ? 'aprobado_revisor' : 'requiere_irving';
        $categoria = $v['categoria_escalada'] ?? null;
        if (! $autoriza && $enPausa && ($v['veredicto'] ?? '') === 'autoriza') {
            $categoria = 'pausa';
        }

        $sello = "\n\n--- REVISOR (#338) " . now()->toDateTimeString() . " ---\n"
            . 'Veredicto: ' . ($autoriza ? 'AUTORIZA → aprobado_revisor' : 'ESCALA → requiere_irving')
            . ' (confianza ' . ($v['confianza'] ?? '?') . ($categoria ? ', ' . $categoria : '') . ")\n"
            . 'Razón: ' . ($v['razon'] ?? '') . "\n"
            . (empty($v['riesgos']) ? '' : 'Riesgos: ' . implode('; ', (array) $v['riesgos']) . "\n");

        $item->estado_aprobacion  = $nuevo;
        $item->comentarios_claude = (string) $item->comentarios_claude . $sello;
        $item->revisado_at        = now();
        $item->aprobado_por       = $actor;
        $item->save();

        if (! empty($v['_audit_id'])) {
            DB::table('circuito_revisiones')->where('id', $v['_audit_id'])
                ->update(['aplicado' => true, 'actor' => mb_substr($actor, 0, 190), 'updated_at' => now()]);
        }

        return $item->fresh();
    }

    private function auditar(RoadmapItem $item, array $v, ?array $meta, ?string $ctx): int
    {
        return (int) DB::table('circuito_revisiones')->insertGetId([
            'roadmap_item_id'    => $item->id,
            'veredicto'          => $v['veredicto'] ?? 'escala',
            'en_alcance'         => (bool) ($v['en_alcance'] ?? false),
            'categoria_escalada' => $v['categoria_escalada'] ?? null,
            'confianza'          => $v['confianza'] ?? null,
            'motivo'             => $v['razon'] ?? null,
            'riesgos'            => json_encode(array_values((array) ($v['riesgos'] ?? [])), JSON_UNESCAPED_UNICODE),
            'modelo'             => $meta['modelo'] ?? null,
            'decisor_ctx'        => $ctx ? mb_substr($ctx, 0, 4000) : null,
            'tokens_in'          => $meta['tokens_in'] ?? null,
            'tokens_out'         => $meta['tokens_out'] ?? null,
            'aplicado'           => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /** Extrae el objeto JSON del veredicto de la respuesta (tolerante a fences/texto extra). */
    private function parse(string $text): array
    {
        $json = null;
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $json = json_decode($m[0], true);
        }
        if (! is_array($json)) {
            // JSON ilegible → falla-segura (escala).
            return [
                'veredicto'          => 'escala',
                'categoria_escalada' => 'duda',
                'confianza'          => 'baja',
                'razon'              => 'Respuesta del revisor no fue JSON legible → escala por seguridad.',
                'riesgos'            => ['parseo fallido'],
            ];
        }

        $ver = ($json['veredicto'] ?? '') === 'autoriza' ? 'autoriza' : 'escala';

        return [
            'veredicto'          => $ver,
            'categoria_escalada' => $ver === 'autoriza' ? null : ($json['categoria_escalada'] ?? 'duda'),
            'confianza'          => in_array($json['confianza'] ?? '', ['alta', 'media', 'baja'], true) ? $json['confianza'] : 'baja',
            'razon'              => mb_strimwidth((string) ($json['razon'] ?? ''), 0, 800, '…'),
            'riesgos'            => array_slice(array_map('strval', (array) ($json['riesgos'] ?? [])), 0, 8),
        ];
    }

    private function systemPrompt(): string
    {
        $base = <<<'TXT'
Eres el REVISOR ADVERSARIAL e INDEPENDIENTE del Circuito CC de MegaISP (un ISP; codebase Laravel 10 / Vue 3 en español). Evalúas una DECISIÓN de nivel B (bug / infra / refactor técnico) que el ejecutor del circuito quiere realizar SOLO, sin Irving. Tu trabajo NO es aprobar a ciegas: es decidir si es CLARAMENTE seguro (autoriza) o si merece el ojo de Irving (escala). Contexto FRESCO: no confíes en el decisor, busca el fallo.

ALCANCE (afinado, arranque ampliado — sé GENEROSO con lo técnico seguro): AUTORIZA con soltura los B claramente TÉCNICOS y de bajo riesgo aunque el alcance sea moderado — p.ej. bugs de null/NPE, rutas/enlaces muertos (404), typos, textos/labels, iconos, refactors locales, guards defensivos, endpoints de estadística/lectura, correcciones de UI no sensibles, código muerto acotado. Estos NO necesitan a Irving.

NO son frontera por sí solos (AUTORÍZALOS si el resto es técnico y acotado): registrar un permiso NUEVO para un endpoint NUEVO (es convención del proyecto, NO cambia permisos existentes ni escala privilegios); mencionar `login_user` como el campo de identidad del login; un CSRF/token de sesión rutinario; una ruta o un `guard` defensivo de código (null-guard, validación). Que el item mencione "permiso", "login" o "token" NO lo hace sensible por sí mismo — juzga si REALMENTE toca la frontera.

FRONTERA DURA (intacta — SIEMPRE escala, con cualquier modelo): dinero / cobros / pagos / facturación / saldos; permisos / roles / autenticación / seguridad / credenciales; producción / despliegue / .env; datos destructivos (drop / truncate / borrado masivo / migrate:fresh); decisión de negocio / estrategia / diseño / arquitectura. También escala si el alcance es amplio/ambiguo, mezcla varias cosas, o no puedes verificar que sea seguro. Ante la duda REAL en la frontera, ESCALA. La IA recomienda; Irving decide lo sensible.

Responde EXCLUSIVAMENTE con un objeto JSON (sin texto extra, sin ```):
{"veredicto":"autoriza"|"escala","razon":"1-2 frases concretas","riesgos":["..."],"categoria_escalada":"dinero"|"seguridad"|"prod"|"negocio"|"amplio"|"duda"|null,"confianza":"alta"|"media"|"baja"}
categoria_escalada = null solo si autorizas. No inventes: si es ambiguo cerca de la frontera, escala con confianza baja.
TXT;

        // Reglas de operación / ADN (estabilidad · minimalismo · balance): premia lo limpio/acotado,
        // marca/escala lo que reescribe código funcional aprobado sin justificación fuerte.
        $reglas = $this->reglasOperacion();
        if ($reglas !== '') {
            $base .= "\n\n=== REGLAS DE ARQUITECTURA (ADN — aplícalas al juzgar el cambio) ===\n" . $reglas
                . "\nAl evaluar: un cambio que REESCRIBE código funcional ya aprobado SIN justificación fuerte → ESCALA (riesgo de romper). Premia lo mínimo/aditivo/limpio; penaliza la sobre-ingeniería y los saltos innecesarios.";
        }

        // Perfil vivo de Irving (inlineado): alinea el criterio → menos falsos positivos de escalación.
        $perfil = $this->perfilIrving();
        if ($perfil !== '') {
            $base .= "\n\n=== PERFIL DE DECISIONES DE IRVING (úsalo para decidir a su gusto; reduce escalaciones de lo que él ya resolvería igual, PERO no relaja la frontera dura) ===\n" . $perfil;
        }

        return $base;
    }

    /** Carga el perfil de decisiones de Irving (docs/perfil-decisiones-irving.md). Vacío si falta. */
    private function perfilIrving(): string
    {
        $path = (string) config('circuito.revisor.perfil_path', base_path('docs/perfil-decisiones-irving.md'));
        if (! is_file($path)) {
            return '';
        }

        return mb_strimwidth(trim((string) @file_get_contents($path)), 0, 6000, "\n…(perfil truncado)");
    }

    /**
     * Reglas de operación / ADN (estabilidad · minimalismo · balance) — doc vivo inyectable en los
     * prompts. Afinan el CÓMO de todo cambio; NO relajan la frontera dura. Editable por Irving.
     */
    private function reglasOperacion(): string
    {
        $path = base_path('docs/reglas-operacion-circuito.md');
        if (! is_file($path)) {
            return '';
        }

        return mb_strimwidth(trim((string) @file_get_contents($path)), 0, 3000, "\n…(reglas truncadas)");
    }

    /**
     * BRIEF de decisión para un item C (arquitectura/negocio): NO se auto-autoriza — Opus prepara
     * riesgos/opciones/recomendación para que IRVING decida. Escribe el brief en comentarios_claude
     * y deja el item en requiere_irving. Falla-segura: si la IA falla, solo deja nota + requiere_irving.
     */
    public function briefarC(RoadmapItem $item): array
    {
        $hard = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');
        $texto = '';
        $modelo = $hard;
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'       => $hard,
                'max_tokens'  => (int) config('circuito.revisor.brief_tokens', 1100),
                
                'system'      => "Eres asesor técnico de Irving (dueño de un ISP, codebase Laravel/Vue en español). Este item es de nivel C (arquitectura / negocio / decisión de diseño): NO se ejecuta solo, lo decide Irving. Prepárale un BRIEF de decisión BREVE y accionable en español, en markdown, con EXACTAMENTE estas secciones: **Qué se decide** (1-2 frases), **Opciones** (2-3 con pros/contras en una línea c/u), **Riesgos** (bullets), **Recomendación** (1 opción + por qué, alineada a sus preferencias: conservador, aditivo/reversible, dev-primero, no romper prod). Sé concreto, sin relleno. Considera el perfil de Irving:\n\n" . $this->perfilIrving(),
                'messages'    => [['role' => 'user', 'content' => $this->userPrompt($item, null)]],
            ]);
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $texto .= $blk['text'] ?? '';
                }
            }
        } catch (\Throwable $e) {
            $texto = '(No se pudo generar el brief automáticamente: ' . mb_strimwidth($e->getMessage(), 0, 160, '…') . ')';
        }

        $sello = "\n\n--- BRIEF DE DECISIÓN (C, Opus) " . now()->toDateTimeString() . " ---\n" . trim($texto) . "\n";
        $item->estado_aprobacion  = 'requiere_irving';
        $item->comentarios_claude = (string) $item->comentarios_claude . $sello;
        $item->revisado_at        = now();
        $item->aprobado_por       = 'revisor(brief-C)';
        $item->save();
        $this->auditar($item, ['veredicto' => 'escala', 'en_alcance' => false, 'categoria_escalada' => 'negocio',
            'confianza' => 'alta', 'razon' => 'Brief de decisión C para Irving', 'riesgos' => []], ['modelo' => $modelo], null);

        return ['ok' => true, 'modelo' => $modelo, 'brief' => trim($texto)];
    }

    /**
     * PASADA DE RIESGO (Opus): clasifica un candidato de seguridad/dinero y, si aplica, prepara un
     * FIX + BRIEF accionable para la bandeja de Irving. NO muta el item (el comando decide qué hacer
     * con la clasificación) — solo devuelve datos. FRONTERA DURA: seguridad/dinero/negocio/prod NO
     * se auto-ejecutan; van a Irving. Devuelve:
     *   { categoria: seguridad|dinero|negocio|prod|no_aplica, subcat, severidad: critica|alta|media|baja,
     *     titulo_corto, texto_brief (markdown), modelo }
     */
    public function briefarSeguridad(RoadmapItem $item): array
    {
        $hard = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');
        $sys = "Eres el AUDITOR DE RIESGO (Opus) del Circuito CC de MegaISP (ISP; Laravel 10/Vue; español; login por login_user; passwords base64 no bcrypt; permisos Spatie). Se te da un item del backlog. Clasifícalo por RIESGO y, si es de SEGURIDAD o DINERO real, prepara un FIX + BRIEF para que Irving lo apruebe rápido.\n"
            . "CATEGORÍAS:\n"
            . "- seguridad: vuln real de auth/permisos/roles (rol nuevo=todos los permisos, syncRoles destructivo, escalada de privilegio), IDOR/PII/LFPDPPP, auth bypass / endpoint sin gate / expuesto en PUBLIC_ROUTES, /register público sobre staff, account takeover, reset de contraseña sin OTP, contraseñas en texto plano, webhook forjable (sin HMAC/firma), credenciales/API keys expuestas o hardcodeadas.\n"
            . "- dinero: doble cobro / falta de idempotencia / doble submit, recobro de la misma factura, saldo incorrecto, add_by inexistente en pagos, conciliación que pierde dinero.\n"
            . "- negocio: decisión de estrategia/política/precios/paquetes o una migración con trade-off que SOLO Irving decide (p.ej. estrategia de migrar plano→bcrypt). Marca aquí lo ya rotulado [BLOCKED-NEGOCIO].\n"
            . "- prod: toca PRODUCCIÓN / despliegue / .env de prod / infra del servidor productivo. Marca aquí lo ya rotulado [PARKED-PROD]. NO se toca desde dev.\n"
            . "- no_aplica: NO es de seguridad ni dinero (feature, refactor, UI, reliability menor, falso positivo del filtro).\n"
            . "REGLAS: ante duda entre seguridad/dinero y no_aplica cuando HAY exposición real → elige el riesgo. Si es claramente feature/UI → no_aplica. El fix debe ser CONCRETO, aditivo/reversible, dev-primero, sin romper prod; nombra archivos/rutas si el item los da.\n"
            . "Responde EXCLUSIVAMENTE JSON (sin ```): {\"categoria\":\"seguridad|dinero|negocio|prod|no_aplica\",\"subcat\":\"etiqueta corta (p.ej. idor_pii, auth_bypass, roles_privilegio, passwords, webhook, secrets, idempotencia, doble_cobro)\",\"severidad\":\"critica|alta|media|baja\",\"titulo_corto\":\"5-9 palabras\",\"brief\":\"si seguridad/dinero: markdown con **Vulnerabilidad** (qué), **Impacto** (quién puede qué), **Fix** (pasos concretos aditivos/reversibles), **Riesgo del fix** (regresión a vigilar); si negocio: **Qué decide Irving** + **Opciones**; si prod: 1 frase de por qué está parkeado; si no_aplica: cadena vacía\"}\n\n"
            . "Perfil de decisiones de Irving:\n" . $this->perfilIrving();

        $modelo = $hard;
        $v = ['categoria' => 'no_aplica', 'subcat' => '', 'severidad' => 'media', 'titulo_corto' => '', 'texto_brief' => '', 'modelo' => $modelo];
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model' => $hard, 'max_tokens' => (int) config('circuito.revisor.brief_tokens', 1100),
                'system' => $sys, 'messages' => [['role' => 'user', 'content' => $this->userPrompt($item, (string) $item->comentarios_claude)]],
            ]);
            $text = '';
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') { $text .= $blk['text'] ?? ''; }
            }
            if (preg_match('/\{.*\}/s', $text, $m) && is_array($j = json_decode($m[0], true))) {
                $cat = in_array($j['categoria'] ?? '', ['seguridad', 'dinero', 'negocio', 'prod', 'no_aplica'], true) ? $j['categoria'] : 'no_aplica';
                $sev = in_array($j['severidad'] ?? '', ['critica', 'alta', 'media', 'baja'], true) ? $j['severidad'] : 'media';
                $v = [
                    'categoria'    => $cat,
                    'subcat'       => (string) ($j['subcat'] ?? ''),
                    'severidad'    => $sev,
                    'titulo_corto' => (string) ($j['titulo_corto'] ?? ''),
                    'texto_brief'  => (string) ($j['brief'] ?? ''),
                    'modelo'       => $modelo,
                ];
            }
        } catch (\Throwable $e) {
            $v['texto_brief'] = '(Auditor de riesgo no concluyente: ' . mb_strimwidth($e->getMessage(), 0, 140, '…') . ' → queda para Irving.)';
            $v['categoria'] = 'seguridad'; // falla-segura: ante fallo, trátalo como sensible (a Irving), no lo bajes
        }

        return $v;
    }

    /**
     * DES-TRABE (Opus) de un item de la bandeja (requiere_irving): re-clasifica y decide si el circuito
     * puede resolverlo SOLO. tecnico_seguro / falso-positivo → RE-APRUEBA (vuelve al pool). negocio/
     * dinero/seguridad/prod → queda en requiere_irving con TAG + BRIEF de Opus para Irving. Auditable.
     * FRONTERA DURA intacta: ante duda real en lo sensible → NO reejecutable.
     */
    public function destrabar(RoadmapItem $item): array
    {
        $hard = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');
        $sys = "Eres el DES-TRABADOR (Opus) de la bandeja de Irving en el Circuito CC de MegaISP (ISP; Laravel/Vue; español). Este item quedó ESCALADO (requiere_irving). Re-evalúalo con criterio y decide su CATEGORÍA y si el circuito puede resolverlo SOLO:\n"
            . "- tecnico_seguro: bug / refactor local / código muerto / guard de null / ruta o enlace muerto / typo / texto/label / UI NO sensible; ADITIVO y reversible; O una escalación que fue FALSO POSITIVO (el revisor lo mandó por un término amplio de la denylist, no por riesgo real). INCLUYE explícitamente: registrar un permiso NUEVO para un endpoint NUEVO (convención del proyecto — no cambia permisos existentes ni escala privilegios), mencionar `login_user` como campo de identidad, o un token CSRF/sesión rutinario. Que el item diga 'permiso'/'login'/'token' NO lo hace sensible por sí mismo. → el circuito lo ejecuta solo (reejecutable=true).\n"
            . "- negocio: decisión de negocio/estrategia/política/precios/paquetes/'qué quiere Irving' → SOLO Irving decide (reejecutable=false; Opus NO inventa su criterio).\n"
            . "- dinero: toca dinero/cobros/pagos/facturación/saldos → Irving confirma (reejecutable=false).\n"
            . "- seguridad: CAMBIAR/otorgar permisos o roles EXISTENTES, escalada de privilegios, syncRoles destructivo, auth bypass / endpoint sin gate, IDOR/PII, credenciales/keys expuestas → Irving confirma (reejecutable=false). [NO cuenta el mero registrar-un-permiso-nuevo, que es tecnico_seguro.]\n"
            . "- prod: producción/despliegue/.env/pipeline de release → Irving confirma (reejecutable=false).\n"
            . "FRONTERA DURA: ante CUALQUIER duda real en dinero/seguridad/prod/negocio → reejecutable=false. Razona el porqué en 1-2 frases.\n"
            . "Responde EXCLUSIVAMENTE JSON (sin ```): {\"categoria\":\"tecnico_seguro|negocio|dinero|seguridad|prod\",\"reejecutable\":true|false,\"razon\":\"por qué, 1-2 frases\",\"brief\":\"si reejecutable=false: markdown corto — **Qué se decide** / **Opciones** (con pros-contras) / **Recomendación**; si reejecutable=true: cadena vacía\"}\n\n"
            . "Perfil de decisiones de Irving:\n" . $this->perfilIrving();

        $modelo = $hard;
        $v = ['categoria' => 'negocio', 'reejecutable' => false, 'razon' => '', 'brief' => ''];
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model' => $hard, 'max_tokens' => (int) config('circuito.revisor.brief_tokens', 1100),
                'system' => $sys, 'messages' => [['role' => 'user', 'content' => $this->userPrompt($item, (string) $item->comentarios_claude)]],
            ]);
            $text = '';
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') { $text .= $blk['text'] ?? ''; }
            }
            if (preg_match('/\{.*\}/s', $text, $m) && is_array($j = json_decode($m[0], true))) {
                $cat = in_array($j['categoria'] ?? '', ['tecnico_seguro', 'negocio', 'dinero', 'seguridad', 'prod'], true) ? $j['categoria'] : 'negocio';
                $v = ['categoria' => $cat, 'reejecutable' => (bool) ($j['reejecutable'] ?? false) && $cat === 'tecnico_seguro',
                    'razon' => (string) ($j['razon'] ?? ''), 'brief' => (string) ($j['brief'] ?? '')];
            }
        } catch (\Throwable $e) {
            $v['razon'] = 'Des-trabador no concluyente (' . mb_strimwidth($e->getMessage(), 0, 140, '…') . ') → queda para Irving.';
        }

        if ($v['reejecutable']) {
            // Técnico/seguro (o falso positivo) → vuelve al POOL. A→aprobado_claude, B→aprobado_revisor.
            $item->estado_aprobacion = $item->nivel_riesgo === 'A' ? 'aprobado_claude' : 'aprobado_revisor';
            $item->comentarios_claude = (string) $item->comentarios_claude
                . "\n\n--- DES-TRABE (Opus) " . now()->toDateTimeString() . " → RE-APROBADO (tecnico_seguro) ---\nRazón: " . trim($v['razon']) . "\n";
            $item->aprobado_por = 'destrabe(opus)';
        } else {
            // Genuinamente de Irving → queda con TAG de categoría + brief para despacho en lote.
            $item->estado_aprobacion = 'requiere_irving';
            $item->comentarios_claude = (string) $item->comentarios_claude
                . "\n\n--- DES-TRABE (Opus) " . now()->toDateTimeString() . " [CATEGORIA:" . $v['categoria'] . "] ---\nRazón: " . trim($v['razon']) . "\n"
                . (trim($v['brief']) !== '' ? "Brief:\n" . trim($v['brief']) . "\n" : '');
            $item->aprobado_por = 'destrabe(opus)';
        }
        $item->revisado_at = now();
        $item->save();

        return ['categoria' => $v['categoria'], 'reejecutable' => $v['reejecutable'], 'razon' => trim($v['razon']), 'modelo' => $modelo];
    }

    private function userPrompt(RoadmapItem $item, ?string $decisorCtx): string
    {
        $prompt = mb_strimwidth((string) $item->prompt, 0, 1800, '…');
        $ctx    = $decisorCtx !== null && trim($decisorCtx) !== ''
            ? mb_strimwidth(trim($decisorCtx), 0, 1200, '…')
            : '(no se proporcionó contexto del decisor; evalúa el item en sí)';

        return "ITEM #{$item->id} (nivel B) — módulo: " . ($item->modulo ?: '(sin módulo)') . "\n"
            . 'Título: ' . $item->title . "\n\n"
            . "Descripción / plan:\n" . $prompt . "\n\n"
            . "Contexto del decisor (su justificación técnica):\n" . $ctx . "\n\n"
            . '¿El circuito puede EJECUTAR esto solo (autoriza) o debe ir a Irving (escala)? Responde solo el JSON.';
    }
}
