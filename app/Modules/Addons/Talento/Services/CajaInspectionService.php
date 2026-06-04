<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CajaInspectionService
{
    public function __construct(private ClaudeApiClient $claude) {}

    /**
     * Run IA aesthetic analysis on a caja inspection photo.
     * Returns advisory flags only — supervisor must validate.
     * Graceful degradation: empty flags if IA unavailable.
     */
    public function analyzePhoto(TalentoCajaInspection $inspection): array
    {
        if (!$inspection->photo_path) {
            return ['flags' => [], 'summary' => 'Sin foto disponible.'];
        }

        try {
            $contents = Storage::disk('local')->get($inspection->photo_path);
            if (!$contents) {
                return ['flags' => [], 'summary' => 'Archivo de foto no encontrado.'];
            }

            $base64   = base64_encode($contents);
            $mimeType = Storage::disk('local')->mimeType($inspection->photo_path) ?: 'image/jpeg';

            $prompt = <<<'TXT'
Eres un inspector técnico de redes de fibra óptica. Analiza esta fotografía de una caja de distribución óptica (ODB/Splitter).

Evalúa los siguientes puntos y responde ÚNICAMENTE en JSON con esta estructura exacta:
{
  "aesthetic_score": <entero 1-10>,
  "flags": [
    { "aspect": "<nombre del aspecto>", "issue": "<descripción breve>", "severity": "ok|warning|error" }
  ],
  "summary": "<resumen en 1-2 oraciones en español>"
}

Aspectos a evaluar:
1. Organización de cables (sin cruces, curvaturas adecuadas)
2. Estado de la bandeja/raqueta de empalmes
3. Limpieza y ausencia de suciedad o humedad
4. Etiquetado visible y legible
5. Hermeticidad aparente (si se aprecia)

Si la imagen no permite evaluar algún aspecto, indícalo como "no_visible" en severity.
TXT;

            $response = $this->claude->messages([
                'model'      => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
                'max_tokens' => 512,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64,
                            ],
                        ],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]);

            $text = $response['content'][0]['text'] ?? '{}';

            // Extract JSON even if IA prepends/appends text
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $parsed = json_decode($m[0], true);
            }

            if (!isset($parsed['flags'])) {
                return ['flags' => [], 'summary' => 'Análisis IA no disponible.'];
            }

            return [
                'flags'           => $parsed['flags'],
                'summary'         => $parsed['summary'] ?? '',
                'aesthetic_score' => $parsed['aesthetic_score'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('CajaInspectionService IA analysis failed', [
                'inspection_id' => $inspection->id,
                'error'         => $e->getMessage(),
            ]);
            return ['flags' => [], 'summary' => 'Análisis IA no disponible (error de conexión).'];
        }
    }

    /**
     * Compute overall_result based on measured values vs standards.
     * Power and fusion_loss drive objective result; aesthetic is advisory.
     */
    public function computeResult(
        ?float $fusionLoss,
        ?float $powerDbm,
        ?int   $aestheticScore
    ): string {
        $issues = 0;

        if ($fusionLoss !== null && $fusionLoss > 0.5) {
            $issues++;
        }
        if ($powerDbm !== null && ($powerDbm < -28 || $powerDbm > -10)) {
            $issues++;
        }
        if ($aestheticScore !== null && $aestheticScore < 4) {
            $issues++;
        }

        if ($issues === 0) {
            return 'pass';
        }
        if ($issues >= 2) {
            return 'fail';
        }
        return 'needs_rework';
    }
}
