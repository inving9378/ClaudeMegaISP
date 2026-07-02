<?php

namespace App\Modules\Addons\Payments\Services\Extraction\Profiles;

use App\Modules\Addons\Payments\Services\Extraction\ReceiptProfileInterface;

/**
 * FASE PAGOS 3 (pieza 2) — Perfil de extracción para comprobantes de
 * transferencia SPEI (México). Extrae 5 campos con confianza por campo.
 */
class SpeiTransferProfile implements ReceiptProfileInterface
{
    public const TYPE = 'spei_transfer';

    public function type(): string
    {
        return self::TYPE;
    }

    public function fields(): array
    {
        return [
            'clave_rastreo',
            'monto',
            'fecha',
            'titular_ordenante',
            'banco_origen',
        ];
    }

    public function prompt(): string
    {
        return <<<'TXT'
Eres un extractor de datos de comprobantes de transferencia SPEI (México).
Analiza la imagen del comprobante y extrae ÚNICAMENTE estos 5 campos:
- clave_rastreo: la clave de rastreo SPEI (cadena alfanumérica larga, a veces llamada "clave de rastreo" o "folio SPEI").
- monto: el importe transferido, SOLO el número con decimales (ej. "1250.00"), sin símbolo de moneda ni comas de miles.
- fecha: la fecha de la operación, tal como aparece en el comprobante.
- titular_ordenante: nombre del ordenante (quien ENVÍA el dinero).
- banco_origen: banco emisor/ordenante (de donde SALE el dinero).

REGLAS CRÍTICAS (obligatorias, sin excepción):
1. NUNCA inventes ni adivines. Si un campo NO está clara y legiblemente visible en la imagen, pon "value": null y "confidence": "baja". Es mucho mejor "no lo pude leer" que un dato inventado.
2. Extrae SOLO lo que REALMENTE ves en la imagen. Prohibido completar con conocimiento externo, suposiciones o valores "típicos".
3. "monto" y "clave_rastreo" son los datos MÁS críticos (de ellos depende el dinero). Ante la MÍNIMA duda en cualquiera de ellos, usa "confidence": "baja".
4. "confidence" debe ser exactamente uno de:
   - "alta"  = el dato se lee con total claridad, sin ninguna duda.
   - "media" = legible pero con alguna duda (calidad, reflejo, dígito ambiguo).
   - "baja"  = dudoso o no legible.
5. Si la imagen NO es un comprobante SPEI, o está totalmente ilegible, devuelve los 5 campos con "value": null y "confidence": "baja".

Responde EXCLUSIVAMENTE con un JSON válido, sin texto antes ni después, con ESTA estructura EXACTA:
{
  "fields": {
    "clave_rastreo":     {"value": null, "confidence": "baja"},
    "monto":             {"value": null, "confidence": "baja"},
    "fecha":             {"value": null, "confidence": "baja"},
    "titular_ordenante": {"value": null, "confidence": "baja"},
    "banco_origen":      {"value": null, "confidence": "baja"}
  }
}
(Reemplaza cada value/confidence por lo que realmente leas, respetando las reglas anteriores.)
TXT;
    }
}
