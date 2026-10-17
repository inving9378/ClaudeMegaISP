<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingDocumentoEvidencia as Evidencia;
use App\Models\Invoice;
use App\Modules\Addons\WhatsAppAgent\Services\WhatsAppGateway;

/**
 * FAC-09 (item #516): manda por WhatsApp (API oficial de Meta, plantilla de utilidad con el PDF
 * como encabezado) EL MISMO documento que el ícono y «Enviar» — Invoice::documentoVigente() +
 * PrefacturaPdfService. Por el gateway único (WhatsAppGateway), nunca un cliente HTTP propio.
 *
 * Apagado por defecto (billing.whatsapp_documento.activo) y además sujeto a la reactivación por
 * etapas (FAC-08): WhatsApp necesita su propia aprobación. El PDF sube a Meta como media (sin URL
 * pública). Cada intento deja evidencia (canal whatsapp): si ya hay uno aceptado o en curso para el
 * mismo documento LÓGICO (clave: factura + documento + su estado; el sha256 no sirve porque dompdf
 * incrusta la fecha de creación), no se repite — un resultado incierto no autoriza reenviar.
 */
class DocumentoWhatsAppService
{
    public function __construct(
        private readonly PrefacturaPdfService $pdf,
        private readonly WhatsAppGateway $gateway,
        private readonly EvidenciaDocumentoService $evidencias,
        private readonly ReactivacionEnvios $etapa,
    ) {
    }

    /** @return array{status: string, motivo: string, evidencia_id?: ?int, message_id?: ?string} */
    public function enviar(Invoice $invoice, ?string $telefono = null): array
    {
        $cfg = (array) config('billing.whatsapp_documento', []);
        if (empty($cfg['activo'])) {
            return ['status' => 'apagado', 'motivo' => 'billing.whatsapp_documento.activo = false'];
        }
        if (! $this->etapa->permiteWhatsApp()) {
            return ['status' => 'no_aprobado', 'motivo' => 'WhatsApp no está aprobado en la etapa de reactivación (FAC-08)'];
        }
        [$permitido, $motivo] = $this->etapa->permiteCliente((int) $invoice->client_id);
        if (! $permitido) {
            return ['status' => 'fuera_de_etapa', 'motivo' => $motivo];
        }

        $documento = $invoice->documentoVigente();
        if ($documento === null) {
            return ['status' => 'sin_documento', 'motivo' => 'factura cancelada'];
        }
        if ($documento === Invoice::DOC_CFDI) {
            return ['status' => 'sin_documento', 'motivo' => 'CFDI: su envío llega con la Fase 4'];
        }
        $pagada = $documento === Invoice::DOC_FACTURA;

        $telefono = $telefono ? self::normalizar($telefono) : self::telefonoDeLaFicha($invoice);
        if (! $telefono) {
            return ['status' => 'sin_telefono', 'motivo' => 'la ficha no tiene un teléfono de 10 dígitos'];
        }

        $r = $this->pdf->generar($invoice, $pagada ? PrefacturaDataProvider::ESTADO_PAGADO : PrefacturaDataProvider::ESTADO_PENDIENTE);
        if (($r['status'] ?? null) !== 'ok') {
            return ['status' => 'error', 'motivo' => 'el PDF no se pudo generar'];
        }
        $tipo = $pagada ? 'factura' : 'prefactura';
        $clave = self::clave($invoice, $tipo);

        $previo = Evidencia::where('canal', 'whatsapp')->where('clave', $clave)
            ->whereIn('estado', [Evidencia::ESTADO_GENERADO, Evidencia::ESTADO_ACEPTADO])->first();
        if ($previo) {
            return ['status' => 'ya_enviado', 'motivo' => "ya hay un envío {$previo->estado} del mismo documento", 'evidencia_id' => $previo->id];
        }

        $cmi = $invoice->client?->client_main_information;
        $variables = [
            trim(($cmi->name ?? '') . ' ' . ($cmi->father_last_name ?? '')) ?: 'cliente',
            PrefacturaDataProvider::periodoLegible($invoice->period) ?: (string) $invoice->number,
            PrefacturaDataProvider::dinero((float) ($pagada ? $invoice->total : ($invoice->pending_balance ?: $invoice->total))),
        ];

        $evidencia = $this->evidencias->registrar(['data' => $r['pdf'], 'name' => $r['filename']], [
            'origen' => Evidencia::ORIGEN_AUTOMATICO, 'documento' => $tipo, 'canal' => 'whatsapp', 'clave' => $clave,
            'client_id' => $invoice->client_id, 'invoice_id' => $invoice->id, 'payment_id' => $invoice->payment_id,
            'destinatario' => self::enmascarar($telefono),
            'asunto' => $pagada ? ($cfg['plantilla_factura'] ?? '') : ($cfg['plantilla_prefactura'] ?? ''),
        ]);

        try {
            $resp = $this->gateway->sendDocumentTemplate(
                $cfg['linea'] ?? null,
                $telefono,
                (string) ($pagada ? ($cfg['plantilla_factura'] ?? '') : ($cfg['plantilla_prefactura'] ?? '')),
                (string) ($cfg['idioma'] ?? 'es_MX'),
                $r['pdf'],
                $r['filename'],
                $variables,
            );
        } catch (\Throwable $e) {
            if ($evidencia) {
                $this->evidencias->marcarFallido([$evidencia->id], $e->getMessage());
            }

            return ['status' => 'error', 'motivo' => mb_substr($e->getMessage(), 0, 300), 'evidencia_id' => $evidencia?->id];
        }

        // «aceptado» = Meta aceptó el mensaje; la entrega se confirmaría por webhook de estados (no aquí).
        $evidencia?->update(['estado' => Evidencia::ESTADO_ACEPTADO, 'aceptado_at' => now(), 'message_id' => $resp['message_id']]);

        return ['status' => 'enviado', 'motivo' => 'aceptado por Meta', 'evidencia_id' => $evidencia?->id, 'message_id' => $resp['message_id']];
    }

    /** Huella del documento lógico: misma factura, mismo documento y mismo estado = mismo envío. */
    public static function clave(Invoice $invoice, string $documento): string
    {
        return hash('sha256', implode('|', ['whatsapp', $invoice->id, $documento, $invoice->status,
            number_format((float) $invoice->pending_balance, 2, '.', ''), number_format((float) $invoice->total, 2, '.', ''),
            (string) $invoice->updated_at]));
    }

    /** Primer teléfono de la ficha con 10+ dígitos, en formato 521… (mismo criterio que el respaldo de billing:send). */
    public static function telefonoDeLaFicha(Invoice $invoice): ?string
    {
        $cmi = $invoice->client?->client_main_information;
        foreach ([$cmi->phone ?? null, $cmi->phone2 ?? null, $cmi->phone3 ?? null] as $candidato) {
            if ($n = self::normalizar((string) $candidato)) {
                return $n;
            }
        }

        return null;
    }

    public static function normalizar(string $telefono): ?string
    {
        $d = preg_replace('/\D/', '', $telefono);
        if (strlen($d) === 10) {
            return '521' . $d;
        }
        if (strlen($d) === 12 && str_starts_with($d, '52')) {
            return '521' . substr($d, 2);
        }

        return strlen($d) === 13 && str_starts_with($d, '521') ? $d : null;
    }

    /** Para la evidencia basta saber a qué número fue sin guardarlo completo. */
    public static function enmascarar(string $telefono): string
    {
        return str_repeat('•', max(0, strlen($telefono) - 4)) . substr($telefono, -4);
    }
}
