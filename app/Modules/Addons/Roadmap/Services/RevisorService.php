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

        // 2) Revisor adversarial (Claude Sonnet, contexto fresco).
        $model = (string) config('circuito.revisor.model', 'claude-sonnet-4-6');
        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'       => $model,
                'max_tokens'  => (int) config('circuito.revisor.max_tokens', 700),
                'temperature' => 0,
                'system'      => $this->systemPrompt(),
                'messages'    => [[
                    'role'    => 'user',
                    'content' => $this->userPrompt($item, $decisorCtx),
                ]],
            ]);

            $text = '';
            foreach ((array) ($resp['content'] ?? []) as $blk) {
                if (($blk['type'] ?? '') === 'text') {
                    $text .= $blk['text'] ?? '';
                }
            }

            $v = $this->parse($text);
            $v['en_alcance'] = true;
            $v['_audit_id']  = $this->auditar($item, $v, [
                'modelo'     => $model,
                'tokens_in'  => $resp['usage']['input_tokens'] ?? null,
                'tokens_out' => $resp['usage']['output_tokens'] ?? null,
            ], $decisorCtx);

            return $v;
        } catch (\Throwable $e) {
            // Falla-segura: IA caída o respuesta ilegible → ESCALA con confianza baja.
            $v = [
                'veredicto'          => 'escala',
                'en_alcance'         => true,
                'categoria_escalada' => 'duda',
                'confianza'          => 'baja',
                'razon'              => 'El revisor no pudo emitir veredicto (error/respuesta ilegible): '
                    . mb_strimwidth($e->getMessage(), 0, 180, '…') . '. Falla-segura → escala a Irving.',
                'riesgos'            => ['revisor no concluyente'],
            ];
            $v['_audit_id'] = $this->auditar($item, $v, ['modelo' => $model], $decisorCtx);

            return $v;
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
        return <<<'TXT'
Eres el REVISOR ADVERSARIAL e INDEPENDIENTE del Circuito CC de MegaISP (un ISP; codebase Laravel 10 / Vue 3 en español). Evalúas una DECISIÓN de nivel B (bug / infra / refactor técnico) que el ejecutor del circuito quiere realizar SOLO, sin Irving. Tu trabajo NO es aprobar: es intentar REFUTAR que sea seguro. Contexto FRESCO: no confíes en el decisor, busca el fallo.

Autoriza ("autoriza") SOLO si TODO es cierto: es un cambio técnico / de corrección; ADITIVO y reversible; de alcance ACOTADO y claro; verificable con regresión cero; y NO toca dinero / cobros / pagos / facturación / saldos, NI permisos / roles / autenticación / seguridad, NI producción / despliegue, NI datos destructivos (drop / truncate / borrado masivo / migrate:fresh), NI es decisión de negocio / estrategia / diseño / arquitectura.

Escala ("escala") SIEMPRE que toque alguna de esas fronteras duras, o el alcance sea amplio / ambiguo, o mezcle varias cosas, o no puedas verificar que sea seguro, o tengas CUALQUIER duda. Ante la duda, ESCALA. La IA recomienda; Irving decide lo dudoso.

Responde EXCLUSIVAMENTE con un objeto JSON (sin texto extra, sin ```):
{"veredicto":"autoriza"|"escala","razon":"1-2 frases concretas","riesgos":["..."],"categoria_escalada":"dinero"|"seguridad"|"prod"|"negocio"|"amplio"|"duda"|null,"confianza":"alta"|"media"|"baja"}
categoria_escalada = null solo si autorizas. Nunca inventes: si el item es ambiguo, escala con confianza baja.
TXT;
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
