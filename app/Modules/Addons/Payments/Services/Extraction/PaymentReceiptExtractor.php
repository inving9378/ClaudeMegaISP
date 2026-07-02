<?php

namespace App\Modules\Addons\Payments\Services\Extraction;

use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use Illuminate\Support\Facades\Log;

/**
 * FASE PAGOS 3 (pieza 2) — Motor de extracción de datos de comprobantes por IA.
 *
 * ALCANCE: SOLO lee una imagen y devuelve los campos estructurados con su
 * confianza. NO aplica pagos, NO busca cliente, NO decide nada. Reusa
 * ClaudeApiClient y el patrón de visión probado en Talento
 * (FieldIaValidationService / CajaInspectionService).
 *
 * Extensible por perfiles: para soportar Oxxo/CEP se agrega un
 * ReceiptProfileInterface y se registra en $profiles; el motor no cambia.
 */
class PaymentReceiptExtractor
{
    private const CONFIDENCES = ['alta', 'media', 'baja'];

    /** Perfiles registrados por tipo de documento. */
    private array $profiles;

    public function __construct(private ClaudeApiClient $claude)
    {
        $this->profiles = [
            SpeiTransferProfile::TYPE => new SpeiTransferProfile(),
        ];
    }

    /** Tipos de documento soportados (para la UI de prueba). */
    public function supportedTypes(): array
    {
        return array_keys($this->profiles);
    }

    /**
     * Extrae los campos de un comprobante.
     *
     * NUNCA inventa: un campo no legible → value=null + confidence='baja' y se
     * lista en 'unreadable'. Cualquier error (API caída, key inválida, JSON
     * malformado) → ok=false + 'error' con mensaje claro, jamás datos inventados.
     *
     * Acepta imagen (JPEG/PNG/WebP) Y PDF. Claude lee el PDF de forma nativa
     * (bloque 'document'); para imagen usa el bloque 'image'. Se decide por el
     * mime del archivo guardado. Los comprobantes PDF pueden tener varias
     * páginas: al mandar el PDF completo, Claude las lee todas y encuentra el
     * dato esté en la página que esté.
     *
     * @param string $fileBytes     Contenido binario del comprobante (imagen o PDF).
     * @param string $mimeType      Ej. image/jpeg, image/png, application/pdf.
     * @param string $documentType  Perfil de extracción (default spei_transfer).
     * @return array {document_type, ok, fields:{campo:{value,confidence}}, unreadable[], error, raw}
     */
    public function extract(
        string $fileBytes,
        string $mimeType,
        string $documentType = SpeiTransferProfile::TYPE
    ): array {
        $profile = $this->profiles[$documentType] ?? null;
        if (!$profile) {
            return $this->fail($documentType, "Tipo de documento no soportado: {$documentType}");
        }

        if ($fileBytes === '') {
            return $this->fail($documentType, 'El comprobante está vacío o no se pudo leer.');
        }

        try {
            $response = $this->claude->messages([
                'model'      => env('CLAUDE_MODEL', 'claude-sonnet-4-6'),
                'max_tokens' => 1024,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        $this->mediaBlock($fileBytes, $mimeType),
                        ['type' => 'text', 'text' => $profile->prompt()],
                    ],
                ]],
            ]);

            $raw = $response['content'][0]['text'] ?? '';
            return $this->parse($profile, $raw);
        } catch (\Throwable $e) {
            Log::channel('claude')->warning('PaymentReceiptExtractor falló', [
                'document_type' => $documentType,
                'error'         => $e->getMessage(),
            ]);
            return $this->fail(
                $documentType,
                'No se pudo procesar el comprobante con la IA: ' . $e->getMessage()
            );
        }
    }

    /**
     * Arma el bloque de contenido para Claude según el tipo de archivo:
     * - application/pdf → bloque 'document' (Claude lee el PDF nativo, multipágina).
     * - imagen (jpeg/png/webp/…) → bloque 'image'.
     * El ClaudeApiClient reenvía tal cual; no requiere cambios.
     */
    private function mediaBlock(string $fileBytes, string $mimeType): array
    {
        $mime = strtolower(trim($mimeType));

        if ($mime === 'application/pdf') {
            return [
                'type'   => 'document',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'application/pdf',
                    'data'       => base64_encode($fileBytes),
                ],
            ];
        }

        return [
            'type'   => 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => $mime ?: 'image/jpeg',
                'data'       => base64_encode($fileBytes),
            ],
        ];
    }

    /**
     * Parseo robusto: extrae el JSON aunque venga con texto alrededor y valida
     * la estructura. Si no es interpretable → ok=false (nunca inventa datos).
     */
    private function parse(ReceiptProfileInterface $profile, string $raw): array
    {
        $parsed = null;
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $parsed = json_decode($m[0], true);
        }

        if (!is_array($parsed) || !isset($parsed['fields']) || !is_array($parsed['fields'])) {
            return $this->fail(
                $profile->type(),
                'La IA devolvió una respuesta que no se pudo interpretar (JSON inválido o incompleto).',
                $raw
            );
        }

        $fields     = [];
        $unreadable = [];

        foreach ($profile->fields() as $key) {
            $entry = is_array($parsed['fields'][$key] ?? null) ? $parsed['fields'][$key] : [];

            $value = $entry['value'] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' || strtolower($value) === 'null') {
                    $value = null;
                }
            } elseif (is_numeric($value)) {
                $value = (string) $value;
            } elseif ($value !== null) {
                // Cualquier tipo raro (array/bool) se descarta: no inventamos.
                $value = null;
            }

            $confidence = strtolower((string) ($entry['confidence'] ?? 'baja'));
            if (!in_array($confidence, self::CONFIDENCES, true)) {
                $confidence = 'baja';
            }

            // Anti-invención: sin valor legible → siempre baja + marcado ilegible.
            if ($value === null) {
                $confidence   = 'baja';
                $unreadable[] = $key;
            }

            $fields[$key] = ['value' => $value, 'confidence' => $confidence];
        }

        return [
            'document_type' => $profile->type(),
            'ok'            => true,
            'fields'        => $fields,
            'unreadable'    => $unreadable,
            'error'         => null,
            'raw'           => $raw,
        ];
    }

    private function fail(string $type, string $error, string $raw = ''): array
    {
        return [
            'document_type' => $type,
            'ok'            => false,
            'fields'        => [],
            'unreadable'    => [],
            'error'         => $error,
            'raw'           => $raw,
        ];
    }
}
