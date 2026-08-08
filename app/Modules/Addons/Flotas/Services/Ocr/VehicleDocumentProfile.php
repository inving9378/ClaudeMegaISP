<?php

namespace App\Modules\Addons\Flotas\Services\Ocr;

/**
 * Perfil de extracción de un DOCUMENTO DE VEHÍCULO (item #580, Fase 7).
 *
 * Los campos son exactamente columnas reales de `fleet_documents` — no se inventa
 * ninguna. `cost` queda FUERA a propósito: es lo que Meganet pagó, casi nunca viene
 * impreso en el documento, y pedirlo invita a la IA a inventar un número.
 *
 * Mismo patrón que el perfil de comprobantes de Payments (ReceiptProfileInterface):
 * soportar otro tipo de documento = agregar otro perfil, el motor no cambia.
 */
class VehicleDocumentProfile
{
    /** Valores válidos del enum `fleet_documents.document_type`, con su nombre real en México. */
    public const TIPOS = [
        'circulation_card' => 'Tarjeta de circulación',
        'insurance_policy' => 'Póliza de seguro',
        'tenencia'         => 'Tenencia o refrendo vehicular',
        'verification'     => 'Verificación vehicular (holograma)',
        'operator_license' => 'Licencia de conducir',
        'special_permit'   => 'Permiso especial',
        'other'            => 'Otro',
    ];

    /** Campos a extraer, en orden de despliegue. Todos existen en `fleet_documents`. */
    public function fields(): array
    {
        return ['document_type', 'folio_number', 'issued_by', 'issue_date', 'expiration_date'];
    }

    /** Etiquetas para la UI de confirmación. */
    public function labels(): array
    {
        return [
            'document_type'   => 'Tipo de documento',
            'folio_number'    => 'Folio / número',
            'issued_by'       => 'Emisor',
            'issue_date'      => 'Fecha de emisión',
            'expiration_date' => 'Fecha de vencimiento',
        ];
    }

    /**
     * Prompt ESTRICTO anti-invención. La regla de oro es la misma que en el extractor de
     * comprobantes: ante cualquier duda, `null` + confianza `baja`. Un dato inventado en la
     * fecha de vencimiento es peor que un campo vacío, porque de ahí salen las alertas.
     */
    public function prompt(): string
    {
        $tipos = '';
        foreach (self::TIPOS as $clave => $nombre) {
            $tipos .= "  - \"{$clave}\": {$nombre}\n";
        }

        return <<<PROMPT
Eres un extractor de datos de DOCUMENTOS VEHICULARES MEXICANOS. Se te entrega la imagen o el PDF
de un documento. Devuelve ÚNICAMENTE un objeto JSON, sin texto alrededor y sin ```.

Formato EXACTO de la respuesta:
{
  "fields": {
    "document_type":   {"value": null, "confidence": "baja"},
    "folio_number":    {"value": null, "confidence": "baja"},
    "issued_by":       {"value": null, "confidence": "baja"},
    "issue_date":      {"value": null, "confidence": "baja"},
    "expiration_date": {"value": null, "confidence": "baja"}
  }
}

REGLAS INQUEBRANTABLES:
1. NUNCA inventes ni deduzcas un dato que no esté escrito en el documento. Si un campo no se lee
   con claridad, pon "value": null y "confidence": "baja". Un campo vacío es CORRECTO; un campo
   inventado es un error grave.
2. "confidence" solo puede ser "alta", "media" o "baja".
   - "alta": el dato se lee nítido y sin ambigüedad.
   - "media": se lee pero hay algo de duda (borroso, cortado, formato raro).
   - "baja": no se lee, o estarías adivinando.
3. La FECHA DE VENCIMIENTO es el campo crítico (de ahí salen las alertas). Ante la MÍNIMA duda
   sobre ella, usa "baja".

QUÉ ES CADA CAMPO:
- "document_type": una de estas claves EXACTAS (no inventes otras, no traduzcas):
{$tipos}
  Si no puedes determinar de qué documento se trata, usa null (NO uses "other" para salir del paso;
  "other" es solo para un documento vehicular real que claramente no encaja en los anteriores).
- "folio_number": el folio, número de póliza, número de licencia o número de serie del documento
  (NO el número de serie del vehículo VIN, NO las placas).
- "issued_by": quién lo emite (aseguradora, Secretaría de Movilidad, estado, verificentro…).
- "issue_date": fecha de emisión / expedición / inicio de vigencia.
- "expiration_date": fecha de vencimiento / fin de vigencia.

FORMATO DE FECHAS:
- Devuélvelas SIEMPRE como "YYYY-MM-DD".
- Los documentos mexicanos suelen imprimirlas como DD/MM/AAAA: "05/09/2026" es 2026-09-05
  (5 de septiembre), NUNCA 2026-05-09. Si el orden es ambiguo y no puedes resolverlo por contexto,
  usa null con "baja".
- Si el documento solo indica un periodo de vigencia (ej. "vigencia 2026"), no lo conviertas en una
  fecha exacta: null con "baja".
PROMPT;
    }
}
