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

    /**
     * #419 — Frontera dura para el TRIAJE de items nivel NULL. Términos INEQUÍVOCOS (match por
     * substring sobre el heno en minúsculas): dinero/cobros/pagos/facturación, fiscal
     * (Medussa/CFDI/Facturama), producción/deploy, permisos/Spatie, auth/passwords, migraciones
     * destructivas. Los cortos/ambiguos van aparte (self::TRIAJE_C_WORD, con límite de palabra).
     */
    public const TRIAJE_C_PLAIN = [
        'dinero', 'cobro', 'cobros', 'pago', 'pagos', 'factura', 'facturación', 'facturacion',
        'medussa', 'cfdi', 'facturama', 'fiscal', 'timbrado',
        'producción', 'produccion', 'deploy', 'despliegue', 'remote:deploy',
        'permiso', 'permisos', 'spatie', 'login', 'password', 'passwords', 'contraseña', 'contrasena', 'credencial', 'bcrypt',
        'migración destructiva', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'truncate', 'delete from', 'drop table', 'drop column', 'destructiv',
    ];

    /** #419 — Cortos/ambiguos: SOLO con límite de palabra (evita "control"→rol, "privado"→iva, "producto"→prod). */
    public const TRIAJE_C_WORD = ['iva', 'rol', 'roles', 'auth', 'sat', 'prod'];

    /**
     * #419 — Triaje DETERMINISTA de NIVEL para un item con nivel_riesgo NULL (punto ciego: ni el
     * ejecutor —pide A— ni el revisor —pide B— lo toman). SIN IA y SIN autorizar nada. Devuelve la
     * propuesta, NO escribe. NUNCA asigna A (A solo por decisión humana explícita).
     *  - C (→ requiere_irving): frontera dura (dinero/fiscal/prod/permisos/auth/migración destructiva)
     *    o marcador [BLOCKED-*]/[PARKED-*] en el título.
     *  - B (→ sigue pendiente_revision): default seguro; la SIGUIENTE pasada del revisor lo evalúa como B.
     */
    public function triarNivelNull(RoadmapItem $item): array
    {
        // Marcador de bloqueo en el TÍTULO → C directo.
        if (preg_match('/\[(BLOCKED|PARKED)-/i', (string) $item->title)) {
            return ['nivel' => 'C', 'estado' => 'requiere_irving', 'match' => '[BLOCKED-/PARKED-]',
                'motivo' => 'Título con marcador [BLOCKED-*]/[PARKED-*] → decisión de Irving.'];
        }

        $heno = mb_strtolower(trim(($item->title ?? '') . "\n" . ($item->modulo ?? '')
            . "\n" . ($item->description ?? '') . "\n" . ($item->prompt ?? '')));

        foreach (self::TRIAJE_C_PLAIN as $kw) {
            if ($kw !== '' && str_contains($heno, $kw)) {
                return ['nivel' => 'C', 'estado' => 'requiere_irving', 'match' => $kw,
                    'motivo' => "Frontera dura: menciona \"{$kw}\" → nivel C, requiere_irving."];
            }
        }
        foreach (self::TRIAJE_C_WORD as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $heno)) {
                return ['nivel' => 'C', 'estado' => 'requiere_irving', 'match' => $kw,
                    'motivo' => "Frontera dura: menciona \"{$kw}\" → nivel C, requiere_irving."];
            }
        }

        return ['nivel' => 'B', 'estado' => 'pendiente_revision', 'match' => null,
            'motivo' => 'Sin frontera dura → nivel B (default seguro); lo evaluará la próxima pasada B.'];
    }

    /**
     * #419 — Aplica el triaje de nivel a un item NULL (idempotente: tras escribir deja de ser null,
     * no re-entra). Escribe nivel_riesgo + nivel_riesgo_origen='interno' + (si C) estado→requiere_irving.
     * NUNCA lo hace ejecutable: B queda pendiente_revision (el ejecutor solo toma A+pendiente_revision).
     */
    public function aplicarTriajeNull(RoadmapItem $item, array $t, string $actor = 'revisor:triaje-null'): RoadmapItem
    {
        $sello = "\n\n--- TRIAJE NIVEL NULL (#419) " . now()->toDateTimeString() . " ---\n"
            . "Asignado nivel_riesgo={$t['nivel']} (origen interno). Estado → {$t['estado']}.\n"
            . 'Motivo: ' . ($t['motivo'] ?? '') . "\n";

        $item->nivel_riesgo        = $t['nivel'];
        $item->nivel_riesgo_origen = 'interno';
        $item->estado_aprobacion   = $t['estado'];
        $item->comentarios_claude  = (string) $item->comentarios_claude . $sello;
        $item->revisado_at         = now();
        $item->aprobado_por        = $actor;
        $item->save();

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

ALCANCE (afinado, arranque ampliado — sé GENEROSO con lo técnico seguro): AUTORIZA con soltura los B claramente TÉCNICOS y de bajo riesgo aunque el alcance sea moderado — p.ej. bugs de null/NPE, rutas/enlaces muertos (404), typos, textos/labels, iconos, refactors locales, guards defensivos, endpoints de estadística/lectura, correcciones de UI no sensibles, código muerto acotado. Estos NO necesitan a Irving. **TAMBIÉN autoriza lo GRANDE pero NO-sensible y ADITIVO**: arquitectura/mejoras del PROPIO circuito, auditorías y mapas READ-ONLY, documentación, consolidaciones y refactors aditivos/reversibles — AUNQUE sean grandes o 'épicas'. El TAMAÑO por sí solo NO escala.

NO son frontera por sí solos (AUTORÍZALOS si el resto es técnico y acotado): registrar un permiso NUEVO para un endpoint NUEVO (es convención del proyecto, NO cambia permisos existentes ni escala privilegios); mencionar `login_user` como el campo de identidad del login; un CSRF/token de sesión rutinario; una ruta o un `guard` defensivo de código (null-guard, validación). Que el item mencione "permiso", "login" o "token" NO lo hace sensible por sí mismo — juzga si REALMENTE toca la frontera.

FRONTERA DURA (intacta — SIEMPRE escala, con cualquier modelo): dinero / cobros / pagos / facturación / saldos; permisos / roles / autenticación / seguridad / credenciales; producción / despliegue / .env; datos destructivos (drop / truncate / borrado masivo / migrate:fresh); una DECISIÓN de negocio/estrategia/precios/paquetes, o de DISEÑO donde hay que ELEGIR entre opciones con trade-off (NO un refactor/auditoría/doc/arquitectura-aditiva mecánico — eso es técnico). También escala si es AMBIGUO (no sabes qué hacer) o MEZCLA cosas no relacionadas, o no puedes verificar que sea seguro — pero NO por ser solo grande: una épica aditiva acotada al mismo objetivo es autorizable. Ante la duda REAL en la frontera, ESCALA. La IA recomienda; Irving decide lo sensible.

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
     * PROPONE 2-3 OPCIONES concretas para un item C (bandeja interactiva #313). El circuito PROPONE,
     * NO decide: devuelve un array de strings (cada uno = una opción con su pro y su contra en una
     * línea) y NO muta el item — el comando decide si escribir SOLO el campo `opciones`. Nunca toca
     * opcion_elegida (esa la pone Irving) ni ejecuta nada. Falla-segura: IA caída → opciones vacías.
     */
    public function proponerOpciones(RoadmapItem $item): array
    {
        $hard = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');
        $texto = '';
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'      => $hard,
                'max_tokens' => (int) config('circuito.revisor.brief_tokens', 1100),
                'system'     => "Eres asesor técnico de Irving (dueño de un ISP, codebase Laravel/Vue en español). "
                    . "Este item es nivel C (arquitectura / negocio / decisión de diseño): el circuito NO lo ejecuta, lo "
                    . "decide Irving. Propón 2 o 3 OPCIONES concretas y accionables para resolverlo. NO elijas ni "
                    . "recomiendes una (eso lo hace Irving). Responde EXCLUSIVAMENTE un array JSON de strings (sin texto "
                    . "fuera del array, sin markdown). Cada string es UNA opción en una sola línea con este formato: "
                    . "'Opción N: <qué se hace> — Pro: <ventaja breve>. Contra: <riesgo/costo breve>'. Alinéate al perfil "
                    . "de Irving:\n\n" . $this->perfilIrving(),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, null)]],
            ]);
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $texto .= $blk['text'] ?? '';
                }
            }
            $ops = $this->parseOpciones($texto);

            return ['ok' => ! empty($ops), 'opciones' => $ops, 'modelo' => $hard, 'raw' => trim($texto)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'opciones' => [], 'modelo' => $hard, 'error' => mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    /**
     * #432 Fase 3 — BRIEF COMPLETO: propone TODAS las preguntas de decisión del item de una, cada
     * una con sus 2-3 opciones (marca UNA con '(RECOMENDADA)' si aplica) + fase opcional. Devuelve
     * [{id,pregunta,opciones:[str],opcion_elegida:null,fase}]. PROPONE, NO decide. Falla-segura.
     */
    public function proponerPreguntas(RoadmapItem $item): array
    {
        $hard  = (string) config('circuito.revisor.model_hard', 'claude-opus-4-7');
        $texto = '';
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'      => $hard,
                'max_tokens' => (int) config('circuito.revisor.brief_tokens', 1100) * 2,
                'system'     => 'Eres asesor técnico de Irving (dueño de un ISP, codebase Laravel/Vue en español). '
                    . 'Este item es nivel C: lo decide Irving, no el circuito. Desglósalo en TODAS las preguntas de '
                    . 'decisión que Irving debe responder para desbloquearlo de una sola pasada (1 si es una sola '
                    . 'decisión; varias si el item esconde varias). Para CADA pregunta propón 2-3 opciones accionables; '
                    . "marca UNA con '(RECOMENDADA)' al final si hay una preferible (NO elijas tú). Si una decisión "
                    . 'pertenece a una fase posterior, inclúyela igual con "fase":"Fase X". Responde EXCLUSIVAMENTE un '
                    . 'array JSON (sin markdown) de objetos {"pregunta":"...","opciones":["Opción 1: <qué> — Pro: <x>. '
                    . 'Contra: <y>", ...],"fase":null}. Perfil de Irving:' . "\n\n" . $this->perfilIrving(),
                'messages'   => [['role' => 'user', 'content' => $this->userPrompt($item, null)]],
            ]);
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $texto .= $blk['text'] ?? '';
                }
            }
            $pregs = $this->parsePreguntas($texto);

            return ['ok' => ! empty($pregs), 'preguntas' => $pregs, 'modelo' => $hard, 'raw' => trim($texto)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'preguntas' => [], 'modelo' => $hard, 'error' => mb_strimwidth($e->getMessage(), 0, 160, '…')];
        }
    }

    /** Parsea el array JSON de preguntas: cada una {id,pregunta,opciones[str],opcion_elegida:null,fase}. Máx 6. */
    private function parsePreguntas(string $text): array
    {
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }
        $arr = json_decode($m[0], true);
        if (! is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $i => $p) {
            if (! is_array($p)) {
                continue;
            }
            $ops = [];
            foreach ((array) ($p['opciones'] ?? []) as $o) {
                $t = trim((string) (is_array($o) ? ($o['texto'] ?? '') : $o));
                if ($t !== '') {
                    $ops[] = $t;
                }
            }
            if (empty($ops)) {
                continue;
            }
            $out[] = [
                'id'             => 'q' . (count($out) + 1),
                'pregunta'       => trim((string) ($p['pregunta'] ?? '')),
                'opciones'       => array_slice($ops, 0, 4),
                'opcion_elegida' => null,
                'fase'           => ! empty($p['fase']) ? (string) $p['fase'] : null,
            ];
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    /**
     * #432 — escribe el brief completo (columna `preguntas`) SIN tocar opcion_elegida ni ejecutar.
     * Reescribe el brief entero; deja el item en requiere_irving (bandeja). Preserva respuestas ya
     * dadas a preguntas con el MISMO id (para no borrar lo que Irving ya contestó).
     */
    public function aplicarPreguntas(RoadmapItem $item, array $preguntas, string $actor = 'revisor:brief-completo'): RoadmapItem
    {
        $previas = [];
        foreach ((array) $item->preguntas as $p) {
            if (! empty($p['id']) && ! empty($p['opcion_elegida'])) {
                $previas[$p['id']] = $p['opcion_elegida'];
            }
        }
        foreach ($preguntas as &$p) {
            if (isset($previas[$p['id']])) {
                $p['opcion_elegida'] = $previas[$p['id']];
            }
        }
        unset($p);

        $item->preguntas          = $preguntas;
        $item->estado_aprobacion  = 'requiere_irving';
        $item->revisado_at        = now();
        $item->aprobado_por       = $actor;
        $item->save();

        return $item->fresh();
    }

    /** Extrae el array JSON de opciones del texto del modelo (tolera fences ```json). Máx 3, strings no vacíos. */
    private function parseOpciones(string $text): array
    {
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }
        $arr = json_decode($m[0], true);
        if (! is_array($arr)) {
            return [];
        }
        $ops = [];
        foreach ($arr as $x) {
            if (is_string($x) && trim($x) !== '') {
                $ops[] = trim($x);
            }
        }

        return array_slice($ops, 0, 3);
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
            . "- tecnico_seguro: bug / refactor local / código muerto / guard de null / ruta o enlace muerto / typo / texto/label / UI NO sensible; ADITIVO y reversible; O una escalación que fue FALSO POSITIVO (el revisor lo mandó por un término amplio de la denylist, no por riesgo real). INCLUYE explícitamente: registrar un permiso NUEVO para un endpoint NUEVO (convención del proyecto — no cambia permisos existentes ni escala privilegios), mencionar `login_user` como campo de identidad, o un token CSRF/sesión rutinario. Que el item diga 'permiso'/'login'/'token' NO lo hace sensible por sí mismo. **TAMBIÉN es tecnico_seguro lo GRANDE pero NO-sensible y ADITIVO**: arquitectura/mejoras del PROPIO circuito, auditorías y mapas READ-ONLY (de API/módulos/permisos — solo LEER y reportar, sin modificar), documentación, consolidaciones y refactors ADITIVOS/reversibles — AUNQUE SEAN GRANDES o 'épicas'. El TAMAÑO NO lo hace de Irving. → el circuito lo ejecuta solo (reejecutable=true).\n"
            . "- negocio: SOLO una DECISIÓN de negocio/producto/precios/paquetes/estrategia, o una elección de DISEÑO donde un humano DEBE escoger entre opciones con trade-off (no hay respuesta mecánica). Un item GRANDE, una 'épica', una auditoría o un refactor NO son negocio por su tamaño: si son aditivos y no tocan la frontera dura (dinero/seguridad/prod), son tecnico_seguro. 'qué quiere Irving' aplica solo cuando de verdad depende de su gusto/estrategia. → Irving decide (reejecutable=false; Opus NO inventa su criterio).\n"
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

        // ANTI-LOOP (no quemar Max): si el EJECUTOR ya corrió este item y NO ejecutó (lo escaló / no
        // pudo), NO lo re-aprobamos aunque Opus lo crea técnico. El ejecutor es la AUTORIDAD de
        // ejecutabilidad; re-aprobar reabre el ping-pong des-trabador↔ejecutor (p.ej. #170 dead
        // exports: Opus dice "removible", el ejecutor dice "no hay dónde aplicarlo" → loop infinito).
        // Se parquea para Irving con el conteo de rebotes; él re-aprueba a mano si quiere.
        if ($v['reejecutable'] && ($rebotes = $this->rebotesDelEjecutor($item)) > 0) {
            $v['reejecutable'] = false;
            $v['categoria']    = 'ejecutor_no_pudo';
            $v['razon']        = "ANTI-LOOP: el ejecutor ya corrió este item {$rebotes}× y NO lo ejecutó (lo re-escaló). No se re-aprueba, para no quemar Max en el ping-pong; necesita tu decisión o re-especificarlo para hacerlo accionable. " . trim($v['razon']);
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

    /**
     * ANTI-LOOP — cuántas veces el EJECUTOR ya corrió este item y NO ejecutó (ejecuto=0). Si >0,
     * re-aprobarlo reabre el ping-pong des-trabador↔ejecutor. Matchea el id como elemento distinto
     * del arreglo JSON `items_tocados` (evita JSON_CONTAINS por filas legacy no-JSON, y no confunde
     * 170 con 17/1700). Falla-abierta: ante error, 0 (no bloquea).
     */
    private function rebotesDelEjecutor(RoadmapItem $item): int
    {
        $id = (int) $item->id;
        try {
            // items_tocados es JSON (`[249, 170]`). JSON_CONTAINS con el id como literal JSON numérico
            // (string '170') matchea el elemento exacto — no confunde 170 con 17/1700. whereNotNull
            // evita el error "Invalid data type" de filas legacy sin items.
            return (int) \App\Modules\Addons\Roadmap\Models\CircuitoEjecucion::query()
                ->where('ejecuto', false)
                ->whereNotNull('items_tocados')
                ->whereRaw('JSON_CONTAINS(items_tocados, ?)', [(string) $id])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
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
