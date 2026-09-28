<?php

namespace App\Modules\Addons\Payments\Services\Extraction;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\Payments\Services\Extraction\Profiles\SpeiTransferProfile;
use Illuminate\Support\Facades\Log;

/**
 * FASE PAGOS 3 (pieza 2) — Motor de extracción de datos de comprobantes por IA.
 *
 * ALCANCE: SOLO lee una imagen y devuelve los campos estructurados con su
 * confianza. NO aplica pagos, NO busca cliente, NO decide nada.
 *
 * Usa el Hub de IA compartido (IAAdaptadorFactory), mismo patrón ya probado en
 * FleetDocumentOcrService — NO un cliente propio de un solo proveedor. Antes
 * usaba ClaudeApiClient hardcodeado a Anthropic: si esa cuenta se quedaba sin
 * crédito, TODO el módulo de comprobantes quedaba ciego aunque hubiera otro
 * proveedor activo (encontrado 2026-09-28 probando en vivo). Con el Hub, cae
 * a cualquier proveedor activo que soporte imágenes — igual límite ya conocido
 * que Flotas: PDF solo lo lee un proveedor driver=claude (los demás no
 * soportan ese bloque), fotos/imágenes sí con cualquiera.
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
     * Acepta imagen (JPEG/PNG/WebP) con cualquier proveedor activo que soporte
     * imágenes. PDF SOLO lo lee un proveedor driver=claude (los demás
     * adaptadores no soportan ese bloque) — Claude lo lee nativo y multipágina;
     * sin un proveedor Claude activo, un PDF falla con mensaje claro (nunca
     * se intenta como imagen).
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

        $proveedor = $this->resolverProveedor();
        if (!$proveedor) {
            return $this->fail(
                $documentType,
                'No hay un proveedor de IA activo con soporte de imágenes. Configúralo en /ia/configuracion.'
            );
        }

        $mime = strtolower(trim($mimeType));
        if ($mime === 'application/pdf' && $proveedor->driver !== 'claude') {
            return $this->fail(
                $documentType,
                'El proveedor de IA configurado no lee PDF. Sube el comprobante como imagen (JPG/PNG) '
                . 'o activa un proveedor Claude en /ia/configuracion.'
            );
        }

        try {
            $adaptador = IAAdaptadorFactory::crear($proveedor);

            $resultado = $adaptador->enviarMensaje(
                [],
                $profile->prompt(),
                [['mime' => $mime ?: 'image/jpeg', 'data' => base64_encode($fileBytes)]],
                'Responde únicamente con el JSON solicitado, sin explicaciones.'
            );

            return $this->parse($profile, (string) ($resultado['texto'] ?? ''));
        } catch (\Throwable $e) {
            Log::channel('claude')->warning('PaymentReceiptExtractor falló', [
                'document_type' => $documentType,
                'proveedor'     => $proveedor->nombre ?? null,
                'error'         => $e->getMessage(),
            ]);
            return $this->fail(
                $documentType,
                'No se pudo procesar el comprobante con la IA: ' . $e->getMessage()
            );
        }
    }

    /**
     * Proveedor de IA a usar: activo y con soporte de imágenes. Este servicio
     * nunca define credenciales — las toma del catálogo (mismo patrón que
     * FleetDocumentOcrService::resolverProveedor).
     */
    private function resolverProveedor(): ?IAProveedor
    {
        return IAProveedor::where('activo', true)
            ->where('soporta_imagenes', true)
            ->orderBy('id')
            ->first();
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
