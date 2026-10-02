<?php

namespace App\Modules\Addons\Flotas\Services\Ocr;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OCR de documentos de vehículo por IA (item #580, Flotas Fase 7).
 *
 * ALCANCE: SOLO lee el archivo y devuelve campos estructurados con su confianza.
 * NO crea documentos, NO decide nada, NO manda notificaciones. Quien confirma es
 * una persona (ver FleetDocumentController::ocr + la pantalla de Documentos).
 *
 * ⚠️ CONVENCIÓN DE SERVICIOS COMPARTIDOS (CLAUDE.md): la IA vive SOLO en el módulo IA.
 * Este servicio NO tiene cliente HTTP ni API key propios: usa la IA asignada en
 * Integraciones → Módulos IA (clave flotas.ocr, vía IA::enviar). Cambiar proveedor/modelo
 * se hace ahí y este código no se toca. Que el proveedor lea imágenes y PDF lo valida
 * IA (requisitos declarados en config ia_modulos).
 *
 * NUNCA inventa: campo ilegible → value=null + confidence='baja' y entra en `unreadable`.
 * Cualquier error (sin proveedor, API caída, JSON malformado) → ok=false + `error`,
 * jamás datos fabricados, y NUNCA lanza excepción hacia arriba: la subida del documento
 * no se puede bloquear porque la IA falle.
 */
class FleetDocumentOcrService
{
    private const CONFIANZAS = ['alta', 'media', 'baja'];

    /** Orden de severidad para comparar contra el umbral configurado. */
    private const RANKING = ['baja' => 0, 'media' => 1, 'alta' => 2];

    public function __construct(private VehicleDocumentProfile $profile = new VehicleDocumentProfile())
    {
    }

    /**
     * Lee un documento de vehículo.
     *
     * @param  string $bytes  Contenido binario del archivo (imagen o PDF).
     * @param  string $mime   image/jpeg, image/png, image/webp o application/pdf.
     * @return array{ok:bool, fields:array, unreadable:array, needs_review:bool, error:?string,
     *                model:?string, provider:?string, raw:string}
     */
    public function extract(string $bytes, string $mime): array
    {
        $mime = strtolower(trim($mime));

        if (! config('flotas.ocr.enabled', true)) {
            return $this->fail('La lectura por IA está desactivada.');
        }
        if ($bytes === '') {
            return $this->fail('El archivo está vacío o no se pudo leer.');
        }
        if (! in_array($mime, (array) config('flotas.ocr.mimes', []), true)) {
            return $this->fail("Formato no soportado para lectura por IA ({$mime}).");
        }
        if (strlen($bytes) > (int) config('flotas.ocr.max_bytes')) {
            return $this->fail('El archivo supera el tamaño máximo para lectura por IA.');
        }

        try {
            $resultado = IA::enviar(
                'flotas.ocr',
                $this->profile->prompt(),
                [['mime' => $mime, 'data' => base64_encode($bytes)]],
                'Responde únicamente con el JSON solicitado, sin explicaciones.',
                [],                                                   // sin historial: es una lectura de una sola vez
                ['json' => true]
            );

            return $this->parse((string) ($resultado['texto'] ?? ''), $resultado);
        } catch (IANoConfigurada $e) {
            return $this->fail($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('FleetDocumentOcrService: falló la lectura del documento', [
                'mime'  => $mime,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('No se pudo leer el documento con la IA: ' . $e->getMessage());
        }
    }

    /** Etiquetas legibles de los campos (las consume la pantalla de confirmación). */
    public function labels(): array
    {
        return $this->profile->labels();
    }

    /**
     * Parseo robusto: rescata el JSON aunque venga con texto o fences alrededor. Si no es
     * interpretable → ok=false (nunca se inventan datos).
     */
    private function parse(string $raw, array $resultado): array
    {
        $parsed = IA::json($raw);

        if (! is_array($parsed) || ! isset($parsed['fields']) || ! is_array($parsed['fields'])) {
            return $this->fail(
                'La IA devolvió una respuesta que no se pudo interpretar (JSON inválido o incompleto).',
                $resultado,
                $raw
            );
        }

        $fields     = [];
        $unreadable = [];

        foreach ($this->profile->fields() as $key) {
            $entry = is_array($parsed['fields'][$key] ?? null) ? $parsed['fields'][$key] : [];

            $value = $this->normalizarValor($key, $entry['value'] ?? null);

            $confianza = strtolower((string) ($entry['confidence'] ?? 'baja'));
            if (! in_array($confianza, self::CONFIANZAS, true)) {
                $confianza = 'baja';
            }

            // Anti-invención: sin valor utilizable → siempre baja y marcado ilegible.
            if ($value === null) {
                $confianza    = 'baja';
                $unreadable[] = $key;
            }

            $fields[$key] = ['value' => $value, 'confidence' => $confianza];
        }

        return [
            'ok'           => true,
            'fields'       => $fields,
            'unreadable'   => $unreadable,
            'needs_review' => $this->requiereRevision($fields),
            'error'        => null,
            'model'        => $resultado['modelo'] ?? null,
            'provider'     => $resultado['proveedor'] ?? null,
            'raw'          => $raw,
        ];
    }

    /**
     * ¿Hay que marcar el documento "revisar manualmente"?
     *
     * Criterio: la FECHA DE VENCIMIENTO manda, porque es la que alimenta las alertas. Si no se
     * pudo leer, o su confianza queda por debajo de `flotas.ocr.confianza_minima`, el documento
     * se guarda igual pero marcado para que una persona lo revise.
     */
    private function requiereRevision(array $fields): bool
    {
        $venc = $fields['expiration_date'] ?? null;
        if (! $venc || $venc['value'] === null) {
            return true;
        }

        $umbral = strtolower((string) config('flotas.ocr.confianza_minima', 'media'));
        $umbral = in_array($umbral, self::CONFIANZAS, true) ? $umbral : 'media';

        return (self::RANKING[$venc['confidence']] ?? 0) < self::RANKING[$umbral];
    }

    /**
     * Normaliza y VALIDA cada campo. Lo que no pasa la validación se descarta (null): preferimos
     * un hueco a un dato basura que el usuario podría confirmar sin mirar.
     */
    private function normalizarValor(string $key, mixed $value): ?string
    {
        if (is_numeric($value)) {
            $value = (string) $value;
        }
        if (! is_string($value)) {
            return null;    // arreglos, booleanos, null… nada de eso es un dato legible
        }

        $value = trim($value);
        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        if ($key === 'document_type') {
            // Debe ser una clave EXACTA del enum; si la IA improvisó otra cosa, se descarta.
            return array_key_exists($value, VehicleDocumentProfile::TIPOS) ? $value : null;
        }

        if (in_array($key, ['issue_date', 'expiration_date'], true)) {
            return $this->normalizarFecha($value);
        }

        return mb_substr($value, 0, 200);
    }

    /**
     * Acepta ISO (lo que se pide en el prompt) y, defensivamente, el formato impreso en los
     * documentos mexicanos: DD/MM/AAAA o DD-MM-AAAA. NUNCA se interpreta como MM/DD.
     * Cualquier otro formato → null (no se adivina).
     */
    private function normalizarFecha(string $value): ?string
    {
        $fecha = null;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            $fecha = [(int) $m[3], (int) $m[2], (int) $m[1]];               // d, m, Y
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $value, $m)) {
            $fecha = [(int) $m[1], (int) $m[2], (int) $m[3]];               // DD/MM/AAAA
        }

        if (! $fecha || ! checkdate($fecha[1], $fecha[0], $fecha[2])) {
            return null;
        }

        return Carbon::createFromDate($fecha[2], $fecha[1], $fecha[0])->format('Y-m-d');
    }

    private function fail(string $error, ?array $resultado = null, string $raw = ''): array
    {
        return [
            'ok'           => false,
            'fields'       => [],
            'unreadable'   => $this->profile->fields(),
            'needs_review' => true,   // si no se pudo leer, alguien tiene que capturarlo a mano
            'error'        => $error,
            'model'        => $resultado['modelo'] ?? null,
            'provider'     => $resultado['proveedor'] ?? null,
            'raw'          => $raw,
        ];
    }
}
