<?php

namespace App\Services\Finance\Billing;

use App\Models\BillingNotification;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;

/**
 * FAC-04 (item #509, padre #482): qué documento sale en cada notificación de cobro automática,
 * decidido JUSTO ANTES de enviar con el mismo resolver que «Enviar» (Invoice::documentoVigente +
 * PrefacturaPdfService). Nada aquí envía ni cambia la pausa (billing_config.envio_pausado).
 *
 *  - prefactura: si la factura ya está pagada o cancelada → se cancela (no se manda un cobro de
 *    algo pagado). Pendiente → la Prefactura de la plantilla; si la plantilla falla, el PDF
 *    guardado al generarla (mismo criterio que «Enviar»: respaldo solo para pendientes).
 *  - recibo: si el pago dejó PAGADA alguna factura → en su lugar va la Factura (PAGADA) de cada
 *    una; si una no se puede generar, error (sin respaldo, #498). Si solo abonó (parcial) o es
 *    CFDI (aún sin envío, Fase 4) → el recibo de siempre: los parciales no se reinterpretan.
 *  - ticket: sin cambios.
 *
 * Devuelve una decisión; quien envía (billing:send-pending-notifications) la aplica.
 */
class DocumentoNotificacionResolver
{
    public const ENVIAR = 'enviar';
    public const CANCELAR = 'cancelar';
    public const ERROR = 'error';

    public const MOTIVO_PAGADA = 'Cancelada: la factura se pagó; la reemplaza la factura pagada del pago (FAC-04).';

    private bool $generarPdfs = true;

    public function __construct(private readonly PrefacturaPdfService $pdf)
    {
    }

    /**
     * Cancela las notificaciones de prefactura EN ESPERA de esta factura (nunca las enviadas ni
     * las de otras facturas). Idempotente: una segunda llamada no encuentra nada.
     */
    public function cancelarPrefacturasPendientes(Invoice $invoice, string $motivo = self::MOTIVO_PAGADA): int
    {
        return BillingNotification::where('invoice_id', $invoice->id)
            ->where('document_type', BillingNotification::TYPE_PREFACTURA)
            ->where('status', BillingNotification::STATUS_WAITING)
            ->update(['status' => BillingNotification::STATUS_CANCELLED, 'error_log' => $motivo]);
    }

    /**
     * @return array{accion: string, motivo: string, documento: ?array{asunto: string, titulo: string,
     *               mensaje: string, etiqueta: string, folios: string[], adjuntos: array<int, array{data: string, name: string, mime: string}>}}
     */
    /**
     * @param bool $generar false = solo decidir, sin generar PDFs (el --dry-run no debe escribir:
     *                      generar la Prefactura persiste la referencia MEG del cliente).
     */
    public function resolver(BillingNotification $n, bool $generar = true): array
    {
        $this->generarPdfs = $generar;

        return match ($n->document_type) {
            BillingNotification::TYPE_PREFACTURA => $this->prefactura($n),
            BillingNotification::TYPE_RECIBO     => $this->recibo($n),
            default                              => $this->comoSiempre('Documento sin cambios.'),
        };
    }

    private function prefactura(BillingNotification $n): array
    {
        $invoice = $n->invoice_id ? Invoice::find($n->invoice_id) : null;
        if (! $invoice) {
            return $this->decision(self::CANCELAR, 'Cancelada: la factura ya no existe.');
        }

        $vigente = $invoice->documentoVigente();
        if ($vigente === null) {
            return $this->decision(self::CANCELAR, 'Cancelada: la factura está cancelada.');
        }
        if ($vigente !== Invoice::DOC_PREFACTURA) {
            return $this->decision(self::CANCELAR, self::MOTIVO_PAGADA);
        }

        $r = $this->generar($invoice, PrefacturaDataProvider::ESTADO_PENDIENTE);
        if ($r === null) {
            // Respaldo solo para pendientes: el PDF que se guardó al crear la notificación.
            return $this->comoSiempre('La plantilla no se pudo generar; va el PDF guardado.');
        }

        return $this->decision(self::ENVIAR, 'Prefactura vigente.', [
            'asunto'   => PrefacturaPdfService::asuntoCorreo('Tu prefactura', [$invoice]),
            'titulo'   => 'Prefactura',
            'mensaje'  => 'Te compartimos tu prefactura con el saldo a pagar, la fecha límite y los datos para pagar.',
            'etiqueta' => 'PREFACTURA',
            'folios'   => [(string) ($invoice->number ?: $invoice->id)],
            'adjuntos' => [$r],
        ]);
    }

    private function recibo(BillingNotification $n): array
    {
        if (! $n->payment_id) {
            return $this->comoSiempre('Recibo sin pago asociado.');
        }

        $pagadas = Invoice::where('payment_id', $n->payment_id)
            ->where('status', Invoice::STATUS_PAID)
            ->orderBy('period')
            ->get()
            ->filter(fn (Invoice $i) => $i->documentoVigente() === Invoice::DOC_FACTURA)
            ->values();

        if ($pagadas->isEmpty()) {
            return $this->comoSiempre('El pago no dejó facturas pagadas (abono o CFDI): va el recibo.');
        }

        $adjuntos = [];
        foreach ($pagadas as $invoice) {
            $r = $this->generar($invoice, PrefacturaDataProvider::ESTADO_PAGADO);
            if ($r === null) {
                return $this->decision(self::ERROR, "No se envió: la factura pagada {$invoice->id} no se pudo generar (sin respaldo, #498).");
            }
            $adjuntos[] = $r;
        }

        return $this->decision(self::ENVIAR, 'Factura pagada en lugar del recibo.', [
            'asunto'   => PrefacturaPdfService::asuntoCorreo('Tu factura', $pagadas, ' (pagada)'),
            'titulo'   => $pagadas->count() > 1 ? 'Facturas pagadas' : 'Factura pagada',
            'mensaje'  => 'Hemos registrado tu pago. Te compartimos tu factura PAGADA (sin valor fiscal). ¡Gracias por tu pago!',
            'etiqueta' => 'FACTURA (PAGADA)',
            'folios'   => $pagadas->map(fn (Invoice $i) => (string) ($i->number ?: $i->id))->all(),
            'adjuntos' => $adjuntos,
        ]);
    }

    /** @return array{data: string, name: string, mime: string}|null */
    private function generar(Invoice $invoice, string $estado): ?array
    {
        if (! $this->generarPdfs) {
            return ['data' => '', 'name' => '(sin generar: dry-run)', 'mime' => 'application/pdf'];
        }

        try {
            $r = $this->pdf->generar($invoice, $estado);
        } catch (\Throwable $e) {
            Log::warning("[billing:send] factura #{$invoice->id}: error al generar el PDF: " . $e->getMessage());

            return null;
        }
        if (($r['status'] ?? null) !== 'ok') {
            Log::warning("[billing:send] factura #{$invoice->id}: la plantilla no se pudo llenar.", ['faltan' => $r['keys'] ?? []]);

            return null;
        }

        return ['data' => $r['pdf'], 'name' => $r['filename'], 'mime' => 'application/pdf'];
    }

    private function comoSiempre(string $motivo): array
    {
        return $this->decision(self::ENVIAR, $motivo);
    }

    private function decision(string $accion, string $motivo, ?array $documento = null): array
    {
        return ['accion' => $accion, 'motivo' => $motivo, 'documento' => $documento];
    }
}
