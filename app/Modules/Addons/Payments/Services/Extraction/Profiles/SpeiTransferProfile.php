<?php

namespace App\Modules\Addons\Payments\Services\Extraction\Profiles;

use App\Modules\Addons\Payments\Services\Extraction\ReceiptProfileInterface;

/**
 * FASE PAGOS 3 (pieza 2) — Perfil de extracción para comprobantes de
 * transferencia SPEI (México). Extrae 6 campos con confianza por campo.
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
            'referencia',
            'monto',
            'fecha_pago',
            'titular_ordenante',
            'banco_origen',
            'concepto',
        ];
    }

    public function prompt(): string
    {
        return <<<'TXT'
Eres un extractor de datos de comprobantes de transferencia SPEI (México).
Analiza la imagen del comprobante y extrae ÚNICAMENTE estos 7 campos:
- clave_rastreo: la clave de rastreo SPEI (cadena alfanumérica larga, a veces llamada "clave de rastreo" o "folio SPEI").
- referencia: un número de OPERACIÓN o referencia del sistema que identifica la transacción, SOLO cuando NO hay clave de rastreo SPEI (ej. "Número de operación" o "ID de operación" en Mercado Pago, "Folio", "No. de referencia"). Es un identificador generado por el banco/sistema, NO el texto que escribió el pagador (eso es "concepto"). Si hay clave_rastreo, puedes dejar referencia en null.
- monto: el importe transferido, SOLO el número con decimales (ej. "1250.00"), sin símbolo de moneda ni comas de miles.
- fecha_pago: la fecha (y hora si aparece) en que se REALIZÓ el pago/transferencia, tal como aparece en el comprobante.
- titular_ordenante: nombre del TITULAR/DUEÑO de la cuenta que ENVÍA el dinero (el ordenante). Es el nombre asociado a la cuenta emisora.
- banco_origen: banco emisor/ordenante (de donde SALE el dinero).
- concepto: el texto libre que el pagador ESCRIBIÓ al hacer la transferencia. Aparece con etiquetas como "Concepto", "Concepto de pago", "Referencia", "Descripción" o "Motivo". Es lo que la persona tecleó manualmente (puede ser un nombre, un número de cliente, una nota, etc.).

titular_ordenante y concepto son COSAS DISTINTAS y se extraen POR SEPARADO:
- titular_ordenante = quién es el dueño de la cuenta que envía (dato del banco).
- concepto = el texto que el pagador escribió a mano en el campo Concepto/Referencia.
A veces el NOMBRE de la persona aparece en el "concepto" y NO en el titular (o viceversa). Extrae cada uno de donde REALMENTE esté; no copies el mismo valor en ambos salvo que el comprobante realmente lo repita en los dos lugares.

IMPORTANTE sobre el concepto: puede contener una REFERENCIA DE PAGO con formato "MEG-XXXXXXXX-XX" (las letras MEG, un guion, dígitos y otro guion con 2 dígitos). Si aparece algo así en el concepto, cópialo TAL CUAL, exacto, sin alterar ni un carácter.

REGLAS CRÍTICAS (obligatorias, sin excepción):
1. NUNCA inventes ni adivines. Si un campo NO está clara y legiblemente visible en la imagen, pon "value": null y "confidence": "baja". Es mucho mejor "no lo pude leer" que un dato inventado.
2. Extrae SOLO lo que REALMENTE ves en la imagen. Prohibido completar con conocimiento externo, suposiciones o valores "típicos".
3. "monto" y "clave_rastreo" son los datos MÁS críticos (de ellos depende el dinero). Ante la MÍNIMA duda en cualquiera de ellos, usa "confidence": "baja".
4. "confidence" debe ser exactamente uno de:
   - "alta"  = el dato se lee con total claridad, sin ninguna duda.
   - "media" = legible pero con alguna duda (calidad, reflejo, dígito ambiguo).
   - "baja"  = dudoso o no legible.
5. Si la imagen NO es un comprobante SPEI, o está totalmente ilegible, devuelve los 7 campos con "value": null y "confidence": "baja".

Responde EXCLUSIVAMENTE con un JSON válido, sin texto antes ni después, con ESTA estructura EXACTA:
{
  "fields": {
    "clave_rastreo":     {"value": null, "confidence": "baja"},
    "referencia":        {"value": null, "confidence": "baja"},
    "monto":             {"value": null, "confidence": "baja"},
    "fecha_pago":        {"value": null, "confidence": "baja"},
    "titular_ordenante": {"value": null, "confidence": "baja"},
    "banco_origen":      {"value": null, "confidence": "baja"},
    "concepto":          {"value": null, "confidence": "baja"}
  }
}
(Reemplaza cada value/confidence por lo que realmente leas, respetando las reglas anteriores.)
TXT;
    }
}
