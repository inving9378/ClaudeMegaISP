<?php

namespace App\Modules\Addons\Payments\Services\Extraction;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use Illuminate\Support\Facades\Log;

/**
 * FASE PAGOS 3 (pieza 2) — Motor de extracción de datos de comprobantes por IA.
 *
 * ALCANCE: SOLO lee una imagen y devuelve los campos estructurados con su
 * confianza. NO aplica pagos, NO busca cliente, NO decide nada.
 *
 * La IA (integración + modelo) se decide en Integraciones → Módulos IA (clave
 * pagos.comprobantes, vía IA::enviar) — NO un cliente propio de un solo
 * proveedor. Que el proveedor lea imágenes y PDF lo valida IA (requisitos de
 * la clave): OpenAI y Claude leen ambos.
 *
 * Si no se pudo leer, el resultado trae ok=false + 'error' y, si la causa es
 * que no hay IA asignada, 'motivo' = 'sin_ia'. Los jobs de conciliación NO lo
 * tratan como "no es comprobante": avisan a DEV/super-admin con el motivo
 * (ComprobanteNoLeidoNotifier).
 *
 * Extensible por perfiles: para soportar Oxxo/CEP se agrega un
 * ReceiptProfileInterface y se registra en $profiles; el motor no cambia.
 */
class PaymentReceiptExtractor
{
    private const CONFIDENCES = ['alta', 'media', 'baja'];

    /** Perfiles registrados por tipo de documento. */
    private array $profiles;

    public function __construct()
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
     * Acepta imagen (JPEG/PNG/WebP) y PDF con la IA asignada a la clave
     * pagos.comprobantes.
     *
     * @param string $fileBytes     Contenido binario del comprobante (imagen o PDF).
     * @param string $mimeType      Ej. image/jpeg, image/png, application/pdf.
     * @param string $documentType  Perfil de extracción (default spei_transfer).
     * @return array {document_type, ok, fields:{campo:{value,confidence}}, unreadable[], error, motivo, raw, model, provider}
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

        $mime = strtolower(trim($mimeType));

        try {
            $r = IA::enviar(
                'pagos.comprobantes',
                $profile->prompt(),
                [['mime' => $mime ?: 'image/jpeg', 'data' => base64_encode($fileBytes)]],
                'Responde únicamente con el JSON solicitado, sin explicaciones.',
                [],
                ['json' => true]
            );

            return $this->parse($profile, (string) ($r['texto'] ?? '')) + [
                'model'    => $r['modelo'] ?? null,
                'provider' => $r['proveedor'] ?? null,
            ];
        } catch (IANoConfigurada $e) {
            return $this->fail($documentType, $e->getMessage()) + ['motivo' => 'sin_ia'];
        } catch (\Throwable $e) {
            Log::warning('PaymentReceiptExtractor falló', [
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
     * Parseo robusto: extrae el JSON aunque venga con texto alrededor y valida
     * la estructura. Si no es interpretable → ok=false (nunca inventa datos).
     */
    private function parse(ReceiptProfileInterface $profile, string $raw): array
    {
        $parsed = IA::json($raw);

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
