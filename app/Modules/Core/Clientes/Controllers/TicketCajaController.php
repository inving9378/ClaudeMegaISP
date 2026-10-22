<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Dto\EmailDto;
use Illuminate\Routing\Controller;
use App\Models\BillingDocumentoEvidencia;
use App\Models\Payment;
use App\Modules\Core\Configuracion\Services\EmailConfigService;
use App\Notifications\StandardNotification;
use App\Services\Finance\Billing\BillingDocumentService;
use App\Services\Finance\Billing\EvidenciaDocumentoService;
use App\Services\LogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

/**
 * #580 (decisión de Irving 2026-10-22): el ticket de caja (TKT-) es solo para imprimir en caja y ya
 * no se encola. Desde el pago, en la ficha del cliente, se abre para imprimir y se puede reenviar a
 * mano por correo. El envío es una acción explícita de la persona: no pasa por la cola automática
 * ni por su pausa. Queda en el historial del cliente, en activity_log (facturacion-envios) y como
 * evidencia del PDF enviado.
 */
class TicketCajaController extends Controller
{
    public const PERMISO_VER = 'client_payroll_payment_view_client';
    public const PERMISO_ENVIAR = 'client_payroll_payment_add_client';

    public function __construct(private BillingDocumentService $documentos)
    {
    }

    public function ver(int $id)
    {
        $pago = $this->pago($id);

        return view('core-clientes::ticket-caja.ver', [
            'pagoId' => $pago->id,
            'clienteId' => (int) $pago->paymentable_id,
            'folio' => 'TKT-' . str_pad($pago->id, 8, '0', STR_PAD_LEFT),
            'correo' => $this->correo($pago),
            'puedeEnviar' => $this->puede(self::PERMISO_ENVIAR),
        ]);
    }

    public function pdf(int $id)
    {
        $ticket = $this->documentos->ticketPdf($this->pago($id));

        return response($ticket['bytes'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $ticket['nombre'] . '"',
        ]);
    }

    public function enviar(int $id): JsonResponse
    {
        abort_unless($this->puede(self::PERMISO_ENVIAR), 403, 'No tienes permiso para enviar tickets.');
        $pago = $this->pago($id);
        $correo = $this->correo($pago);
        if (! $correo) {
            return response()->json(['message' => 'El cliente no tiene correo en su ficha: el ticket solo se puede imprimir.'], 422);
        }

        $ticket = $this->documentos->ticketPdf($pago);
        $empresa = optional(\App\Modules\Core\Configuracion\Models\CompanyInformation::first())->company_name ?: 'Meganet';
        $asunto = "Tu ticket de pago {$ticket['folio']} — {$empresa}";
        $evidencia = app(EvidenciaDocumentoService::class)->registrar(['data' => $ticket['bytes'], 'name' => $ticket['nombre']], [
            'origen' => BillingDocumentoEvidencia::ORIGEN_MANUAL,
            'documento' => 'ticket',
            'client_id' => (int) $pago->paymentable_id,
            'payment_id' => $pago->id,
            'destinatario' => $correo,
            'asunto' => $asunto,
        ], BillingDocumentoEvidencia::ESTADO_ENCOLADO);

        $email = new EmailDto();
        $email->subject = $asunto;
        $email->html = '<p>Hola,</p><p>Te enviamos el ticket de tu pago ' . e($ticket['folio']) . ' del '
            . e(\Carbon\Carbon::parse($pago->date)->format('d/m/Y')) . ' por $' . number_format((float) $pago->amount, 2) . '.</p><p>' . e($empresa) . '</p>';
        $email->recipient_email = $correo;
        $email->client_id = (int) $pago->paymentable_id;
        $props = (new EmailConfigService())->getEmailProps($email);
        $props['attachments'] = [['data' => base64_encode($ticket['bytes']), 'name' => $ticket['nombre'], 'mime' => 'application/pdf']];
        if ($evidencia) {
            $props['evidencias'] = [$evidencia->id];
        }
        Notification::route('mail', $props['to'])->notify(new StandardNotification($pago, ['mail'], $props));

        $usuario = auth()->user();
        (new LogService())->log($pago->client, "Cliente #{$pago->paymentable_id} Se reenvió el ticket de caja {$ticket['folio']} a {$correo} por el usuario {$usuario->id} el día " . now()->format('Y-m-d'));
        activity('facturacion-envios')->causedBy($usuario)->performedOn($pago)
            ->withProperties(['folio' => $ticket['folio'], 'destinatario' => $correo, 'evidencia_id' => $evidencia?->id])
            ->log('Ticket de caja reenviado a mano');

        return response()->json(['message' => "Ticket {$ticket['folio']} enviado a {$correo}. Sale en unos minutos con la cola de correo."]);
    }

    /** Solo pagos registrados (no borrados) de un cliente. */
    private function pago(int $id): Payment
    {
        $pago = Payment::with(['client.client_main_information', 'payment_method'])->find($id);
        abort_if(! $pago || ! $pago->client, 404, 'No se encontró el pago.');

        return $pago;
    }

    private function correo(Payment $pago): ?string
    {
        $correo = trim((string) ($pago->client?->client_main_information?->email ?? ''));

        return filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : null;
    }

    private function puede(string $permiso): bool
    {
        $usuario = auth()->user();

        return $usuario && ($usuario->isAdmin() || $usuario->isSuperAdmin() || $usuario->can($permiso));
    }
}
