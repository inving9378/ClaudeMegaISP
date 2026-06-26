<?php

namespace App\Modules\Addons\PortalPago\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentLink;
use App\Modules\Addons\PortalPago\Models\PortalPagoPaymentReport;
use App\Modules\Addons\PortalPago\Requests\ReportarPagoRequest;
use App\Modules\Addons\PortalPago\Services\CepValidatorService;
use App\Modules\Addons\PortalPago\Services\Cep\CepConciliationException;
use App\Modules\Addons\PortalPago\Services\PortalPagoLinkService;
use Illuminate\Support\Carbon;

/**
 * Portal público de pago por token. SIN auth. Acceso exclusivamente por el
 * token UUID de la liga. Anti-IDOR: nada enumerable; respuestas neutras 404.
 */
class PublicPagoController extends Controller
{
    public function __construct(private PortalPagoLinkService $links)
    {
    }

    /**
     * GET /f/{token} — pantalla de pago.
     */
    public function show(string $token)
    {
        $link = $this->links->findByTokenForPayment($token);
        if ($link === null) {
            return $this->noDisponible();
        }

        $account = $link->account()->first();
        $invoice = $link->invoice()->first();

        $clienteNombre = $this->nombreCliente($link);
        $concepto      = $invoice && $invoice->number ? "Factura #{$invoice->number}" : 'Servicio Meganet';
        $diasRestantes = $this->diasRestantes($link);

        return view('addon-portal-pago::pago', [
            'link'          => $link,
            'account'       => $account,
            'clienteNombre' => $clienteNombre,
            'concepto'      => $concepto,
            'diasRestantes' => $diasRestantes,
            'montoFmt'      => '$' . number_format((float) $link->monto_esperado, 2),
        ]);
    }

    /**
     * POST /f/{token}/reportar — el cliente reporta su transferencia.
     */
    public function reportar(ReportarPagoRequest $request, string $token)
    {
        $link = $this->links->findByTokenForPayment($token);
        if ($link === null) {
            return $this->noDisponible();
        }

        // Comprobante: SIEMPRE en disco privado, nunca en public/.
        $comprobantePath = null;
        if ($request->hasFile('comprobante')) {
            $comprobantePath = $request->file('comprobante')
                ->store('private/portal-pago/comprobantes', 'local');
        }

        $report = PortalPagoPaymentReport::create([
            'payment_link_id' => $link->id,
            'clave_rastreo'   => $request->input('clave_rastreo'),
            'banco_emisor'    => $request->input('banco_emisor'),
            'fecha_operacion' => $request->date('fecha_operacion'),
            'monto_reportado' => $request->input('monto_reportado'),
            'comprobante_path' => $comprobantePath,
            'estado'          => PortalPagoPaymentReport::ESTADO_PENDIENTE,
        ]);

        if ($link->estado === PortalPagoPaymentLink::ESTADO_PENDIENTE) {
            $link->update(['estado' => PortalPagoPaymentLink::ESTADO_REPORTADO]);
        }

        // Valida CEP y, si las 3 condiciones cuadran, concilia (cadena observer).
        try {
            app(CepValidatorService::class)->validar($report);
        } catch (CepConciliationException $e) {
            // Guard de negocio (clave ya conciliada / factura ya pagada).
            return redirect("/f/{$token}/estado")->with('aviso', $e->getMessage());
        }

        return redirect("/f/{$token}/estado");
    }

    /**
     * Respuesta neutra para token inexistente / expirado / ya conciliado.
     * SIEMPRE el mismo mensaje y código → no se puede enumerar.
     */
    private function noDisponible()
    {
        return response()->view('addon-portal-pago::no_disponible', [], 404);
    }

    private function nombreCliente(PortalPagoPaymentLink $link): string
    {
        $client = $link->client()->first();
        $cmi    = $client?->client_main_information;

        if ($cmi) {
            $nombre = trim(implode(' ', array_filter([
                $cmi->name ?? null,
                $cmi->father_last_name ?? null,
                $cmi->mother_last_name ?? null,
            ])));
            if ($nombre !== '') {
                return $nombre;
            }
        }

        return 'Cliente #' . $link->client_id;
    }

    private function diasRestantes(PortalPagoPaymentLink $link): ?int
    {
        if ($link->expira_at === null) {
            return null;
        }
        $dias = (int) ceil(Carbon::now()->floatDiffInDays($link->expira_at, false));
        return max(0, $dias);
    }
}
