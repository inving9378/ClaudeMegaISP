<?php

namespace App\Modules\Addons\Roadmap\Services\Advisors;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Services\Cobranza\InvoiceAgingService;

/**
 * Piloto mínimo del "consejo asesor" (item #344): UN solo rol (Cobranza), corrida MANUAL
 * bajo demanda (sin agenda todavía — decisión de Irving). Lee SOLO agregados de `invoices`
 * (sin nombres/PII de clientes), le pide a Sonnet 1-3 propuestas concretas en formato
 * métrica→problema→idea→impacto, y las deja en la Hoja de Ruta como items nivel C
 * (requiere_irving) — NUNCA se auto-ejecutan. Frontera dura: solo lectura, nada de dinero.
 */
class CobranzaAdvisorService
{
    public const ROL = 'Cobranza';
    public const MODULO = 'Cobranza / Consejo asesor';
    public const MAX_PROPUESTAS = 3;

    /**
     * Agregados read-only de facturación. Sin PII: nada de nombres/ids de cliente.
     * Delegado a InvoiceAgingService (item #484, Fase 1) — punto único de verdad
     * de aging/buckets, compartido con el futuro armado de campañas.
     */
    public function metricas(): array
    {
        return (new InvoiceAgingService())->metricas();
    }

    /**
     * Corre el asesor: pide propuestas a Sonnet y las crea como items C en la Hoja de Ruta.
     * Falla-segura: si la IA falla o no devuelve nada usable, no crea ningún item.
     */
    public function run(): array
    {
        $metricas = $this->metricas();
        $modelo = (string) config('circuito.revisor.model_routine', 'claude-sonnet-4-6');

        try {
            $resp = (new ClaudeApiClient())->messages([
                'model'      => $modelo,
                'max_tokens' => 1400,
                'system'     => $this->systemPrompt(),
                'messages'   => [['role' => 'user', 'content' => "Datos agregados de cobranza (solo lectura, sin PII):\n\n" . json_encode($metricas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)]],
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_strimwidth($e->getMessage(), 0, 200, '…'), 'creados' => []];
        }

        $texto = '';
        foreach ((array) ($resp['content'] ?? []) as $blk) {
            if (($blk['type'] ?? '') === 'text') {
                $texto .= $blk['text'] ?? '';
            }
        }

        $propuestas = $this->parsePropuestas($texto);
        if (empty($propuestas)) {
            return ['ok' => false, 'error' => 'El modelo no devolvió propuestas parseables.', 'raw' => trim($texto), 'creados' => []];
        }

        $creados = [];
        foreach ($propuestas as $p) {
            $creados[] = $this->crearItem($p, $modelo);
        }

        return ['ok' => true, 'modelo' => $modelo, 'metricas' => $metricas, 'creados' => $creados];
    }

    private function systemPrompt(): string
    {
        return "Eres el asesor de COBRANZA dentro de un 'consejo asesor' de IA para un ISP mexicano (Meganet/MegaISP). "
            . 'Tu ÚNICA fuente son agregados read-only de facturación (sin nombres de clientes). Tu trabajo es '
            . 'PROPONER, nunca ejecutar ni decidir: Irving (dueño) decide si cada propuesta se implementa. '
            . 'Analiza los datos y devuelve de 1 a ' . self::MAX_PROPUESTAS . ' propuestas CONCRETAS de mejora de cobranza/morosidad. '
            . 'Responde EXCLUSIVAMENTE un array JSON (sin markdown, sin texto fuera del array) de objetos con '
            . 'EXACTAMENTE estas llaves: "metrica" (el dato que lo detona), "problema" (qué revela ese dato), '
            . '"idea" (la mejora concreta y accionable), "impacto_estimado" (1 línea, cualitativo o con números si '
            . 'los datos lo permiten). Sé específico y evita relleno genérico. Si los datos no alcanzan para ninguna '
            . 'propuesta seria, devuelve un array vacío [].';
    }

    /** Extrae el array JSON de propuestas (tolera fences ```json). Valida las 4 llaves. Máx MAX_PROPUESTAS. */
    private function parsePropuestas(string $text): array
    {
        if (! preg_match('/\[.*\]/s', $text, $m)) {
            return [];
        }
        $arr = json_decode($m[0], true);
        if (! is_array($arr)) {
            return [];
        }

        $out = [];
        foreach ($arr as $p) {
            if (! is_array($p)) {
                continue;
            }
            $metrica = trim((string) ($p['metrica'] ?? ''));
            $problema = trim((string) ($p['problema'] ?? ''));
            $idea = trim((string) ($p['idea'] ?? ''));
            $impacto = trim((string) ($p['impacto_estimado'] ?? ''));
            if ($metrica === '' || $problema === '' || $idea === '') {
                continue;
            }
            $out[] = compact('metrica', 'problema', 'idea', 'impacto');
        }

        return array_slice($out, 0, self::MAX_PROPUESTAS);
    }

    private function crearItem(array $p, string $modelo): RoadmapItem
    {
        $tituloIdea = mb_strimwidth($p['idea'], 0, 110, '…');

        return RoadmapItem::create([
            'title'              => "[Asesor·" . self::ROL . '] ' . $tituloIdea,
            'modulo'             => self::MODULO,
            'status'             => 'pending',
            'priority'           => 'media',
            'nivel_riesgo'       => 'C',
            'nivel_riesgo_origen' => 'interno',
            'estado_aprobacion'  => 'requiere_irving',
            'prompt'             => "# Propuesta del Asesor de " . self::ROL . " (piloto, corrida manual)\n\n"
                . "**Métrica:** {$p['metrica']}\n\n**Problema detectado:** {$p['problema']}\n\n"
                . "**Idea:** {$p['idea']}\n\n**Impacto estimado:** " . ($p['impacto'] !== '' ? $p['impacto'] : '(no estimado)'),
            'comentarios_claude' => 'Generado por CobranzaAdvisorService (item #344, piloto 1-rol manual) el '
                . now()->toDateTimeString() . ". Modelo: {$modelo}. Fuente: agregados read-only de `invoices` (sin PII). "
                . 'Es una PROPUESTA — no se auto-ejecuta.',
            'log'                => [[
                'ts' => now()->toIso8601String(), 'por' => 'advisor:cobranza',
                'evento' => 'creado_por_asesor', 'rol' => self::ROL,
            ]],
        ]);
    }
}
